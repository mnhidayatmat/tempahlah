<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendBookingConfirmation;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ToyyibpayWebhookController extends Controller
{
    public function handle(Request $request, ToyyibpayClient $client): JsonResponse
    {
        $payload = $request->all();

        $externalId = (string) ($payload['refno'] ?? $payload['billcode'] ?? '');
        if (! $externalId) {
            return response()->json(['error' => 'missing_external_id'], 400);
        }

        if (\App\Models\WebhookEvent::where('provider', 'toyyibpay')->where('external_id', $externalId)->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        DB::beginTransaction();
        try {
            $event = \App\Models\WebhookEvent::create([
                'provider' => 'toyyibpay',
                'event_type' => 'payment.callback',
                'external_id' => $externalId,
                'payload' => $payload,
                'signature_status' => $client->verifyCallback($payload) ? 'verified' : 'unverified',
            ]);

            $payment = Payment::where('gateway_provider', 'toyyibpay')
                ->where('gateway_ref', $payload['billcode'] ?? null)
                ->first();

            if (! $payment) {
                $payment = Payment::where('public_id', $payload['order_id'] ?? null)->first();
            }

            if (! $payment) {
                DB::rollBack();
                return response()->json(['status' => 'payment_not_found'], 404);
            }

            $statusId = (int) ($payload['status_id'] ?? 0);
            $newStatus = match ($statusId) {
                1 => Payment::STATUS_SUCCEEDED,
                3 => Payment::STATUS_FAILED,
                default => Payment::STATUS_PROCESSING,
            };

            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === Payment::STATUS_SUCCEEDED ? now() : null,
                'meta' => array_merge($payment->meta ?? [], ['callback' => $payload]),
            ]);

            PaymentTransaction::create([
                'tenant_id' => $payment->tenant_id,
                'payment_id' => $payment->id,
                'provider' => 'toyyibpay',
                'event_type' => 'payment.callback',
                'external_id' => $externalId,
                'signature_status' => $event->signature_status,
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            if ($newStatus === Payment::STATUS_SUCCEEDED) {
                $this->onPaymentSucceeded($payment);
            }

            $event->update(['processed_at' => now()]);

            DB::commit();

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['error' => 'processing_error'], 500);
        }
    }

    protected function onPaymentSucceeded(Payment $payment): void
    {
        $booking = $payment->booking;
        if (! $booking) {
            return;
        }

        if (in_array($payment->type, [Payment::TYPE_DEPOSIT, Payment::TYPE_FULL])) {
            $wasPending = $booking->status === Booking::STATUS_PENDING;
            $booking->update([
                'deposit_paid_at' => now(),
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            if ($wasPending) {
                SendBookingConfirmation::dispatch($booking->id);
            }
        }

        if ($payment->type === Payment::TYPE_BALANCE) {
            $booking->update(['balance_paid_at' => now()]);
        }
    }
}
