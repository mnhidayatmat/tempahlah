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
    ): PaymentTransaction {
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

        // webhook_events is the global de-dupe table — one row per provider
        // + external_id. PaymentTransaction is the per-payment audit row.
        $replay = WebhookEvent::query()
            ->where('provider', 'toyyibpay')
            ->where('external_id', $externalId)
            ->exists();

        $event = WebhookEvent::create([
            'provider' => 'toyyibpay',
            'event_type' => 'payment.callback',
            'external_id' => $externalId,
            'payload' => $payload,
            'signature_status' => $signatureStatus,
            'processed_at' => $replay ? null : now(),
        ]);

        $tx = PaymentTransaction::create([
            'tenant_id' => $tenantId,
            'payment_id' => $payment?->id,
            'provider' => 'toyyibpay',
            'event_type' => 'webhook.callback',
            'external_id' => $externalId,
            'signature_status' => $signatureStatus,
            'payload' => $payload,
            'flagged' => $signatureStatus !== 'verified' || $flagReason !== null,
            'flag_reason' => $flagReason ?? ($signatureStatus !== 'verified' ? "signature {$signatureStatus}" : null),
            'processed_at' => now(),
        ]);

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
