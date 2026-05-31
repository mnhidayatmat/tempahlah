<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Invoicing\GenerateInvoice;
use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingReceipt;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WebhookEvent;
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

        if ($signatureStatus === 'missing') {
            // Likely DuitNow QR — confirm via server-side query.
            try {
                $check = $client->getBillTransactions((string) ($payload['billcode'] ?? ''));
                ToyyibpayLog::recordApiCall(
                    $payment->tenant_id, $payment, 'getBillTransactions',
                    ['billCode' => $payload['billcode'] ?? ''],
                    $check['response'], $check['http_status'], true
                );
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

            $newStatus = match ((int) ($payload['status'] ?? 0)) {
                1 => Payment::STATUS_SUCCEEDED,
                3 => Payment::STATUS_FAILED,
                default => Payment::STATUS_PROCESSING,
            };

            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === Payment::STATUS_SUCCEEDED ? now() : null,
                'meta' => array_merge($payment->meta ?? [], [
                    'callback' => $payload,
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
        $booking = $payment->booking;
        if (! $booking) return;

        if (in_array($payment->type, [Payment::TYPE_DEPOSIT, Payment::TYPE_FULL], true)) {
            $wasPending = $booking->status === Booking::STATUS_PENDING;
            $booking->update([
                'deposit_paid_at' => $booking->deposit_paid_at ?? now(),
                'status' => Booking::STATUS_CONFIRMED,
            ]);
            if ($wasPending) {
                // 1. Warm confirmation message (existing flow).
                SendBookingConfirmation::dispatch($booking->id);

                // 2. Formal receipt: PDF + email + WhatsApp. Generated
                //    server-to-server so it fires even if the customer
                //    closed their browser before the return-page redirect.
                //    Guarded by try/catch so any PDF/storage hiccup
                //    doesn't 500 the webhook (Toyyibpay would retry).
                try {
                    $receipt = app(GenerateInvoice::class)->execute(
                        $booking->fresh(['property', 'tenant', 'bookingGuests']),
                        $payment,
                        Invoice::TYPE_RECEIPT,
                    );
                    SendBookingReceipt::dispatch($booking->id, $receipt->id, $payment->id);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        if ($payment->type === Payment::TYPE_BALANCE) {
            $booking->update(['balance_paid_at' => now()]);

            // Balance payments also get a receipt (different from the
            // confirmation flow — the booking was already confirmed).
            try {
                $receipt = app(GenerateInvoice::class)->execute(
                    $booking->fresh(['property', 'tenant', 'bookingGuests']),
                    $payment,
                    Invoice::TYPE_RECEIPT,
                );
                SendBookingReceipt::dispatch($booking->id, $receipt->id, $payment->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
