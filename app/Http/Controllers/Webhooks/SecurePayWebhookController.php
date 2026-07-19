<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\AttemptOutcome;
use App\Services\Payments\SecurePay\SecurePayClient;
use App\Services\Payments\SecurePay\SecurePayException;
use App\Services\Payments\SecurePay\SecurePayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SecurePay callback_url receiver — the twin of BillplzWebhookController.
 *
 * Per-tenant flow:
 *   1. Resolve Payment via `order_number` (= payment.public_id, the only
 *      merchant-owned key SecurePay echoes back).
 *   2. From the Payment's tenant, load THAT tenant's checksum token.
 *   3. Verify the checksum HMAC. No token / no checksum → confirm the paid
 *      state server-side via the status API (trustworthy server-to-server).
 *   4. Apply the state change through the shared SettlePaymentSuccess action.
 *   5. Log every step into payment_transactions + webhook_events.
 *
 * The route is public (rate-limited via `throttle:webhook-securepay`) but
 * defended by checksum verification — an attacker who knows the URL still
 * can't move a Payment to SUCCEEDED without the tenant's checksum token.
 */
class SecurePayWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // SecurePay posts a flat form body. The checksum is computed over every
        // param EXCEPT `checksum` itself.
        $body = $request->post();
        $checksum = (string) ($body['checksum'] ?? '');
        unset($body['checksum']);

        $orderNumber = (string) ($body['order_number'] ?? '');
        if ($orderNumber === '') {
            return response()->json(['error' => 'missing_order_number'], 400);
        }

        $externalId = $this->externalId($body, $orderNumber);

        // Idempotency — only against rows we've ALREADY processed.
        if (WebhookEvent::query()
            ->where('provider', 'securepay')
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            SecurePayLog::recordWebhook(0, null, $request->post(), $externalId, 'replay', 'already processed');
            return response()->json(['status' => 'already_processed']);
        }

        // 1. Resolve the Payment + tenant.
        $payment = Payment::withoutGlobalScopes()
            ->where('gateway_provider', 'securepay')
            ->where('public_id', $orderNumber)
            ->first();

        if (! $payment) {
            SecurePayLog::recordWebhook(0, null, $request->post(), $externalId, 'no_payment', 'payment row not found');
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        // 2. Load the tenant-specific client.
        try {
            $client = SecurePayClient::forTenant($payment->tenant_id);
        } catch (SecurePayException $e) {
            SecurePayLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, 'no_creds', $e->getMessage()
            );
            return response()->json(['error' => 'tenant_creds_missing'], 409);
        }

        // 3. Verify the checksum.
        $signatureStatus = $client->callbackSignatureStatus($body, $checksum);

        // A checksum-verified callback is trustworthy, so read the attempt
        // outcome — paid OR declined — straight out of it.
        //
        // When we CAN'T verify it (tenant stored no checksum token, SecurePay
        // sent none, OR the checksum simply doesn't match), we fall back to a
        // server-side status check instead of dropping the callback. That
        // matters: a mismatch is not necessarily a forgery — a genuinely-paid
        // real FPX callback carries a broader field set than our checksum was
        // tested against, so its checksum can legitimately diverge and would
        // otherwise be silently discarded, leaving a paid booking unsettled.
        //
        // The status API is authoritative and unforgeable (Basic-auth'd
        // server-to-server), so trusting it is safe: an attacker POSTing a
        // forged callback with a bad checksum still can't make SecurePay report
        // the order as paid. It can only ever CONFIRM payment — it reports
        // `payment_status: false` both for a declined order and for one nobody
        // has attempted, so a false there is Unknown, never Failed.
        $outcome = $client->attemptOutcome($body);
        if ($signatureStatus !== 'verified') {
            try {
                $check = $client->getPaymentStatus($orderNumber);
            } catch (SecurePayException $e) {
                // 'missing' (unverifiable) → 502 so SecurePay retries later.
                // 'invalid' (checksum mismatch) → we can't confirm it either
                // way, so flag it and stop; a retry or the return-page
                // reconcile can still settle it if it was genuinely paid.
                $reason = $signatureStatus === 'invalid'
                    ? 'checksum mismatch + server check failed: '.$e->getMessage()
                    : 'unverifiable + server-side check failed: '.$e->getMessage();
                SecurePayLog::recordWebhook(
                    $payment->tenant_id, $payment, $request->post(), $externalId, $signatureStatus, $reason
                );
                return response()->json(
                    ['error' => $signatureStatus === 'invalid' ? 'invalid_signature' : 'unverifiable'],
                    $signatureStatus === 'invalid' ? 200 : 502
                );
            }

            if ($signatureStatus === 'invalid' && ! $check['paid']) {
                // Bad checksum AND SecurePay says unpaid → treat as a rejected
                // callback. Don't 4xx — SecurePay would retry, polluting logs.
                SecurePayLog::recordWebhook(
                    $payment->tenant_id, $payment, $request->post(), $externalId, 'invalid',
                    'checksum mismatch, server says unpaid'
                );
                return response()->json(['error' => 'invalid_signature'], 200);
            }

            // Server is authoritative: paid → settle, otherwise Unknown.
            $outcome = $check['paid'] ? AttemptOutcome::Paid : AttemptOutcome::Unknown;
        }

        // 4. Apply the state change in a transaction.
        try {
            DB::beginTransaction();

            $log = SecurePayLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, $signatureStatus
            );

            $paid = $outcome === AttemptOutcome::Paid;

            $payment->update([
                'status' => $paid ? Payment::STATUS_SUCCEEDED : Payment::STATUS_PROCESSING,
                'paid_at' => $paid ? now() : $payment->paid_at,
                'meta' => array_merge($payment->meta ?? [], [
                    'callback' => $request->post(),
                    'callback_received_at' => now()->toIso8601String(),
                ]),
            ]);

            if ($paid) {
                $this->onPaymentSucceeded($payment);
            } elseif ($outcome === AttemptOutcome::Failed) {
                // Stays `processing` — the session is still payable, so the
                // guest retries the same link. This only tells the return page
                // (and the host) that the last attempt was declined.
                $payment->markAttemptFailed();
            }

            $log['event']->update(['processed_at' => now()]);

            DB::commit();
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            SecurePayLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, $signatureStatus,
                'processing_error: '.$e->getMessage()
            );
            return response()->json(['error' => 'processing_error'], 500);
        }
    }

    /**
     * Idempotency key for this callback.
     *
     * `order_number` alone is not enough: SecurePay can legitimately call back
     * more than once for the same order — a failed attempt followed by a
     * successful retry, or B2B1's `fpx_debit_auth_code` 99 (pending approval)
     * followed by 00 (approved). Keying on the order alone would make that
     * second, decisive callback look like a replay and silently drop it.
     *
     * So we key on the order plus SecurePay's own transaction identifier plus
     * the paid/unpaid outcome. A genuine duplicate delivery collapses; a real
     * state transition gets through. The order number leads so the key stays
     * unique against `webhook_events.external_id`, which is globally unique
     * across providers.
     */
    protected function externalId(array $body, string $orderNumber): string
    {
        $txnRef = (string) ($body['payment_id'] ?? $body['exchange_number'] ?? '');
        $outcome = in_array($body['payment_status'] ?? null, [true, 'true', 1, '1'], true) ? 'paid' : 'unpaid';

        return $orderNumber.':'.($txnRef !== '' ? $txnRef : 'na').':'.$outcome;
    }

    protected function onPaymentSucceeded(Payment $payment): void
    {
        // Same shared settlement the Toyyibpay + Billplz webhooks and the
        // payment return page use.
        app(\App\Actions\Payments\SettlePaymentSuccess::class)->execute($payment);
    }
}
