<?php

namespace App\Services\Payments\Toyyibpay;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Centralised audit logging for Toyyibpay.
 *
 * Every meaningful interaction lands in `payment_transactions` with full
 * request + response context and a `flagged` flag for anomalies. Webhook
 * receipts also write to `webhook_events` for idempotency.
 *
 * Sensitive secrets are scrubbed by ToyyibpayClient before reaching here.
 */
class ToyyibpayLog
{
    /**
     * Log an outbound API call (createBill, getBillTransactions, test).
     */
    public static function recordApiCall(
        int $tenantId,
        ?Payment $payment,
        string $eventType,
        array $request,
        ?array $response,
        ?int $httpStatus,
        bool $ok,
        ?string $error = null,
    ): ?PaymentTransaction {
        if ($tenantId <= 0) return null;
        return PaymentTransaction::create([
            'tenant_id' => $tenantId,
            'payment_id' => $payment?->id,
            'provider' => 'toyyibpay',
            'event_type' => "api.{$eventType}",
            'external_id' => $payment?->public_id,
            'signature_status' => 'n/a',
            'payload' => [
                'request' => $request,
                'response' => $response,
                'http_status' => $httpStatus,
                'ok' => $ok,
                'error' => $error,
            ],
            'flagged' => ! $ok,
            'flag_reason' => $ok ? null : ($error ?? "API call {$eventType} failed"),
            'processed_at' => now(),
        ]);
    }

    /**
     * Log an inbound webhook callback. Returns the transaction row + whether
     * this external_id was a replay (already-processed).
     */
    public static function recordWebhook(
        int $tenantId,
        ?Payment $payment,
        array $payload,
        string $signatureStatus,
        ?string $flagReason = null,
    ): array {
        $externalId = (string) (
            $payload['refno']
            ?? $payload['billcode']
            ?? $payload['order_id']
            ?? 'unknown-'.uniqid()
        );

        // webhook_events.external_id is UNIQUE. The first callback for a
        // given refno wins; subsequent replays just touch the existing row
        // (no audit-row insert, no exception).
        $event = WebhookEvent::firstOrCreate(
            [
                'provider' => 'toyyibpay',
                'external_id' => $externalId,
            ],
            [
                'event_type' => 'payment.callback',
                'payload' => $payload,
                'signature_status' => $signatureStatus,
                'processed_at' => null,
            ]
        );
        $replay = ! $event->wasRecentlyCreated;

        // payment_transactions.tenant_id has a NOT NULL FK to tenants. When
        // we don't know the tenant (e.g. unknown payment, replay before
        // resolution), skip the per-payment row — the global webhook_events
        // entry above is enough for that case.
        $tx = null;
        if ($tenantId > 0) {
            // `payment_transactions` is UNIQUE on (provider, external_id).
            // Rejected callbacks (bad signature, missing tenant creds) return
            // early without stamping `processed_at`, so the replay guard in the
            // controller doesn't catch a retry of the same delivery — and
            // Toyyibpay does retry. firstOrCreate keeps that idempotent instead
            // of 500ing on the constraint. Distinct deliveries carry distinct
            // external ids, so genuine events still each get a row.
            //
            // withoutGlobalScopes: the webhook is unauthenticated — the tenant
            // comes from the Payment row, not from request context.
            $tx = PaymentTransaction::withoutGlobalScopes()->firstOrCreate(
                [
                    'provider' => 'toyyibpay',
                    'external_id' => $externalId,
                ],
                [
                    'tenant_id' => $tenantId,
                    'payment_id' => $payment?->id,
                    'event_type' => 'webhook.callback',
                    'signature_status' => $signatureStatus,
                    'payload' => $payload,
                    'flagged' => $signatureStatus !== 'verified' || $flagReason !== null,
                    'flag_reason' => $flagReason ?? ($signatureStatus !== 'verified' ? "signature {$signatureStatus}" : null),
                    'processed_at' => now(),
                ]
            );
        }

        if ($signatureStatus !== 'verified') {
            Log::warning('Toyyibpay webhook signature not verified', [
                'tenant_id' => $tenantId,
                'external_id' => $externalId,
                'status' => $signatureStatus,
                'flag_reason' => $flagReason,
            ]);
        }

        return ['event' => $event, 'transaction' => $tx, 'replay' => $replay];
    }
}
