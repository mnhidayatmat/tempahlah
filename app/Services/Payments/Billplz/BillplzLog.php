<?php

namespace App\Services\Payments\Billplz;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Centralised audit logging for Billplz — the mirror of ToyyibpayLog.
 *
 * Every meaningful interaction lands in `payment_transactions` with full
 * request + response context and a `flagged` flag for anomalies. Webhook
 * receipts also write to `webhook_events` for idempotency.
 */
class BillplzLog
{
    /**
     * Log an outbound API call (createBill, getBill, test).
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
            'provider' => 'billplz',
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
        string $externalId,
        string $signatureStatus,
        ?string $flagReason = null,
    ): array {
        $externalId = $externalId !== '' ? $externalId : 'unknown-'.uniqid();

        // webhook_events.external_id is globally UNIQUE. The first callback for
        // a given bill id wins; replays just touch the existing row.
        $event = WebhookEvent::firstOrCreate(
            [
                'provider' => 'billplz',
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

        $tx = null;
        if ($tenantId > 0) {
            // `payment_transactions` is UNIQUE on (provider, external_id).
            // Rejected callbacks (bad X-Signature, missing tenant creds) return
            // early without stamping `processed_at`, so the replay guard in the
            // controller doesn't catch a retry of the same delivery — and
            // Billplz does retry. firstOrCreate keeps that idempotent instead of
            // 500ing on the constraint. Distinct deliveries carry distinct
            // external ids, so genuine events still each get a row.
            //
            // withoutGlobalScopes: the webhook is unauthenticated — the tenant
            // comes from the Payment row, not from request context.
            $tx = PaymentTransaction::withoutGlobalScopes()->firstOrCreate(
                [
                    'provider' => 'billplz',
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
            Log::warning('Billplz webhook signature not verified', [
                'tenant_id' => $tenantId,
                'external_id' => $externalId,
                'status' => $signatureStatus,
                'flag_reason' => $flagReason,
            ]);
        }

        return ['event' => $event, 'transaction' => $tx, 'replay' => $replay];
    }
}
