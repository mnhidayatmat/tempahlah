<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\Billplz\BillplzException;
use App\Services\Payments\Billplz\BillplzLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Billplz callback_url receiver — the twin of ToyyibpayWebhookController.
 *
 * Per-tenant flow:
 *   1. Resolve Payment via the bill id (`id`) → gateway_ref, else reference_1
 *      (= payment.public_id we set on createBill).
 *   2. From the Payment's tenant, load THAT tenant's Billplz X-Signature key.
 *   3. Verify the X-Signature HMAC. No key / no signature → confirm the paid
 *      state server-side via getBill (trustworthy server-to-server).
 *   4. Apply the state change to Payment + Booking through the shared
 *      SettlePaymentSuccess action.
 *   5. Log every step into payment_transactions + webhook_events.
 *
 * The route is public (rate-limited via `throttle:webhook-billplz`) but
 * defended by signature verification — an attacker who knows the URL still
 * can't move a Payment to SUCCEEDED without the tenant's X-Signature key.
 */
class BillplzWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Billplz posts a flat form body. Signature is computed over every
        // param EXCEPT x_signature.
        $body = $request->post();
        $signature = (string) ($body['x_signature'] ?? '');
        unset($body['x_signature']);

        $billId = (string) ($body['id'] ?? '');
        $reference1 = (string) ($body['reference_1'] ?? '');
        $externalId = $billId !== '' ? $billId : $reference1;

        if ($externalId === '') {
            return response()->json(['error' => 'missing_external_id'], 400);
        }

        // Idempotency — only against rows we've ALREADY processed.
        if (WebhookEvent::query()
            ->where('provider', 'billplz')
            ->where('external_id', $externalId)
            ->whereNotNull('processed_at')
            ->exists()) {
            BillplzLog::recordWebhook(0, null, $request->post(), $externalId, 'replay', 'already processed');
            return response()->json(['status' => 'already_processed']);
        }

        // 1. Resolve the Payment + tenant.
        $payment = $this->resolvePayment($billId, $reference1);
        if (! $payment) {
            BillplzLog::recordWebhook(0, null, $request->post(), $externalId, 'no_payment', 'payment row not found');
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        // 2. Load the tenant-specific client.
        try {
            $client = BillplzClient::forTenant($payment->tenant_id);
        } catch (BillplzException $e) {
            BillplzLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, 'no_creds', $e->getMessage()
            );
            return response()->json(['error' => 'tenant_creds_missing'], 409);
        }

        // 3. Verify the X-Signature.
        $signatureStatus = $client->callbackSignatureStatus($body, $signature);

        if ($signatureStatus === 'invalid') {
            BillplzLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, 'invalid', 'X-Signature mismatch'
            );
            // Don't 401 — Billplz would retry, polluting logs. 200 + flag.
            return response()->json(['error' => 'invalid_signature'], 200);
        }

        // When we can't verify the signature (tenant set no X-Signature key, or
        // Billplz sent none), fall back to a server-side getBill check before
        // trusting `paid`.
        $paidConfirmed = $client->isPaid($body);
        if ($signatureStatus === 'missing') {
            try {
                $check = $client->getBill((string) ($billId !== '' ? $billId : $payment->gateway_ref));
                $paidConfirmed = (bool) $check['paid'];
            } catch (BillplzException $e) {
                BillplzLog::recordWebhook(
                    $payment->tenant_id, $payment, $request->post(), $externalId, 'missing',
                    'unverifiable + server-side check failed: '.$e->getMessage()
                );
                return response()->json(['error' => 'unverifiable'], 502);
            }
        }

        // 4. Apply the state change in a transaction.
        try {
            DB::beginTransaction();

            $log = BillplzLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, $signatureStatus
            );

            $newStatus = $paidConfirmed ? Payment::STATUS_SUCCEEDED : Payment::STATUS_PROCESSING;

            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === Payment::STATUS_SUCCEEDED ? now() : $payment->paid_at,
                'meta' => array_merge($payment->meta ?? [], [
                    'callback' => $request->post(),
                    'callback_received_at' => now()->toIso8601String(),
                ]),
            ]);

            if ($newStatus === Payment::STATUS_SUCCEEDED) {
                $this->onPaymentSucceeded($payment);
            }

            $log['event']->update(['processed_at' => now()]);

            DB::commit();
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            BillplzLog::recordWebhook(
                $payment->tenant_id, $payment, $request->post(), $externalId, $signatureStatus,
                'processing_error: '.$e->getMessage()
            );
            return response()->json(['error' => 'processing_error'], 500);
        }
    }

    protected function resolvePayment(string $billId, string $reference1): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('gateway_provider', 'billplz')
            ->when($billId !== '', fn ($q) => $q->where('gateway_ref', $billId))
            ->when($billId === '' && $reference1 !== '',
                fn ($q) => $q->where('public_id', $reference1))
            ->first();
    }

    protected function onPaymentSucceeded(Payment $payment): void
    {
        // Same shared settlement the Toyyibpay webhook + payment return page use.
        app(\App\Actions\Payments\SettlePaymentSuccess::class)->execute($payment);
    }
}
