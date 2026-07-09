<?php

namespace App\Services\Payments\SecurePay;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Centralised audit logging for SecurePay — the mirror of BillplzLog.
 *
 * Every meaningful interaction lands in `payment_transactions` with full
 * request + response context and a `flagged` flag for anomalies. Webhook
 * receipts also write to `webhook_events` for idempotency.
 */
class SecurePayLog
{
    /**
     * Log an outbound API call (createPayment, getPaymentStatus, test).
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
            'provider' => 'securepay',
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
     * Log an inbound webhook callback. Returns the event row + whether this
     * external_id was a replay (already seen).
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

        // webhook_events.external_id is globally UNIQUE (not per-provider), so
        // the caller composes a key that already carries the order number.
        $event = WebhookEvent::firstOrCreate(
            [
                'provider' => 'securepay',
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
            $tx = PaymentTransaction::create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment?->id,
                'provider' => 'securepay',
                'event_type' => 'webhook.callback',
                'external_id' => $externalId,
                'signature_status' => $signatureStatus,
                'payload' => $payload,
                'flagged' => $signatureStatus !== 'verified' || $flagReason !== null,
                'flag_reason' => $flagReason ?? ($signatureStatus !== 'verified' ? "signature {$signatureStatus}" : null),
                'processed_at' => now(),
            ]);
        }

        if ($signatureStatus !== 'verified') {
            Log::warning('SecurePay webhook checksum not verified', [
                'tenant_id' => $tenantId,
                'external_id' => $externalId,
                'status' => $signatureStatus,
                'flag_reason' => $flagReason,
            ]);
        }

        return ['event' => $event, 'transaction' => $tx, 'replay' => $replay];
    }
}
