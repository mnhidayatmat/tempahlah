<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\AttemptOutcome;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\Payments\Toyyibpay\ToyyibpayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Toyyibpay billCallbackUrl receiver.
 *
 * Per-tenant flow:
 *   1. Look up Payment via `order_id` (= payment.public_id we set on createBill).
 *   2. From the Payment's tenant, load THAT tenant's Toyyibpay secret.
 *   3. Verify the documented MD5 hash signature.
 *   4. Apply state change to Payment + Booking.
 *   5. Log every step into payment_transactions + webhook_events.
 *
 * The route is public (rate-limited via `throttle:webhook-toyyibpay`) but
 * defended by signature verification. Even an attacker who knows our
 * webhook URL can't move a Payment to SUCCEEDED without the tenant's
 * userSecretKey to compute a valid hash.
 */
class ToyyibpayWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $externalId = (string) (
            $payload['refno']
            ?? $payload['billcode']
            ?? $payload['order_id']
            ?? ''
        );
        if ($externalId === '') {
            return response()->json(['error' => 'missing_external_id'], 400);
        }

        // Idempotency check — only against rows we've ALREADY successfully
        // processed. Lets us replay accidentally-dropped events while still
        // logging the duplicate hit.
        if (WebhookEvent::query()
            ->where('provider', 'toyyibpay')
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            ToyyibpayLog::recordWebhook(0, null, $payload, 'replay', 'already processed');
            return response()->json(['status' => 'already_processed']);
        }

        // 1. Resolve the Payment + tenant.
        $payment = $this->resolvePayment($payload);
        if (! $payment) {
            ToyyibpayLog::recordWebhook(0, null, $payload, 'no_payment', 'payment row not found');
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        // 2. Load tenant-specific client (so signature can be verified
        //    against THAT tenant's secret).
        try {
            $client = ToyyibpayClient::forTenant($payment->tenant_id);
        } catch (ToyyibpayException $e) {
            ToyyibpayLog::recordWebhook(
                $payment->tenant_id, $payment, $payload, 'no_creds', $e->getMessage()
            );
            return response()->json(['error' => 'tenant_creds_missing'], 409);
        }

        // 3. Verify MD5 hash. DuitNow QR callbacks have no hash — for those
        //    we fall back to a server-side getBillTransactions check.
        $signatureStatus = $client->signatureStatus($payload);

        if ($signatureStatus === 'invalid') {
            ToyyibpayLog::recordWebhook(
                $payment->tenant_id, $payment, $payload, 'invalid', 'MD5 mismatch'
            );
            // Don't 401 — Toyyibpay would retry, polluting logs. 200 + flag.
            return response()->json(['error' => 'invalid_signature'], 200);
        }

        // A hash-verified callback is trustworthy, so read the attempt outcome
        // — paid OR declined — straight out of it.
        $outcome = $client->attemptOutcome($payload);

        if ($signatureStatus === 'missing') {
            // Likely DuitNow QR — confirm via server-side query, and DECIDE on
            // that answer. An unsigned payload's own `status` must never settle
            // a payment: anyone who knows a billcode could POST status=1.
            try {
                $check = $client->getBillTransactions((string) ($payload['billcode'] ?? ''));
                ToyyibpayLog::recordApiCall(
                    $payment->tenant_id, $payment, 'getBillTransactions',
                    ['billCode' => $payload['billcode'] ?? ''],
                    $check['response'], $check['http_status'], true
                );
                $outcome = $client->transactionsOutcome($check['transactions']);
            } catch (ToyyibpayException $e) {
                ToyyibpayLog::recordWebhook(
                    $payment->tenant_id, $payment, $payload, 'missing',
                    'unverifiable + server-side check failed: '.$e->getMessage()
                );
                return response()->json(['error' => 'unverifiable'], 502);
            }
        }

        // 4. Apply state change in a transaction.
        try {
            DB::beginTransaction();

            $log = ToyyibpayLog::recordWebhook(
                $payment->tenant_id, $payment, $payload, $signatureStatus
            );

            $paid = $outcome === AttemptOutcome::Paid;

            $payment->update([
                'status' => $paid ? Payment::STATUS_SUCCEEDED : Payment::STATUS_PROCESSING,
                'paid_at' => $paid ? now() : $payment->paid_at,
                'meta' => array_merge($payment->meta ?? [], [
                    'callback' => $payload,
                    'callback_received_at' => now()->toIso8601String(),
                ]),
            ]);

            if ($paid) {
                $this->onPaymentSucceeded($payment);
            } elseif ($outcome === AttemptOutcome::Failed) {
                // Stays `processing` — the bill is still payable, so the guest
                // retries the same link. Closing it here would miss the reuse
                // guard in CreateGatewayBill and mint a second live bill.
                $payment->markAttemptFailed();
            }

            $log['event']->update(['processed_at' => now()]);

            DB::commit();
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            ToyyibpayLog::recordWebhook(
                $payment->tenant_id, $payment, $payload, $signatureStatus,
                'processing_error: '.$e->getMessage()
            );
            return response()->json(['error' => 'processing_error'], 500);
        }
    }

    protected function resolvePayment(array $payload): ?Payment
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $billCode = (string) ($payload['billcode'] ?? '');

        return Payment::withoutGlobalScopes()
            ->when($orderId !== '', fn ($q) => $q->where('public_id', $orderId))
            ->when($orderId === '' && $billCode !== '',
                fn ($q) => $q->where('gateway_provider', 'toyyibpay')
                             ->where('gateway_ref', $billCode))
            ->first();
    }

    protected function onPaymentSucceeded(Payment $payment): void
    {
        // Canonical settlement lives in a shared action so the payment return
        // page can run the exact same logic (server-side verify fallback) and
        // a delayed/missing callback never strands a paid booking on "pending".
        app(\App\Actions\Payments\SettlePaymentSuccess::class)->execute($payment);
    }
}
