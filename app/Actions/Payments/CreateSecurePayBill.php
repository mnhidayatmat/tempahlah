<?php

namespace App\Actions\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\SecurePay\SecurePayClient;
use App\Services\Payments\SecurePay\SecurePayException;
use App\Services\Payments\SecurePay\SecurePayLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create (or reuse) a SecurePay payment session for a booking and return the
 * payment URL. The SecurePay twin of CreateBillplzBill / CreateToyyibpayBill —
 * same return contract so the gateway dispatcher treats them interchangeably.
 *
 * Reuse rule: if there's already a `processing` Payment row of the same type
 * with a `gateway_ref` for this booking, hand back the stored URL instead of
 * double-billing the guest.
 *
 * `gateway_ref` holds the SecurePay **order_number** (= the Payment's
 * public_id), because that is the key both the callback and the status
 * endpoint (`/api/v1/payments/status/{order_number}`) are addressed by. The
 * session slug is kept in meta for support/debugging.
 *
 * @see CreateBillplzBill
 */
class CreateSecurePayBill
{
    /**
     * @param  Booking  $booking
     * @param  string   $type    Payment::TYPE_DEPOSIT | TYPE_BALANCE | TYPE_FULL
     * @param  float    $amount  In RM (SecurePay takes a plain decimal, not cents)
     * @return array{payment: Payment, payment_url: string, bill_code: string, reused: bool}
     */
    public function execute(Booking $booking, string $type, float $amount): array
    {
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', $type)
            ->where('gateway_provider', 'securepay')
            ->where('status', Payment::STATUS_PROCESSING)
            ->whereNotNull('gateway_ref')
            ->first();

        if ($existing && ! empty($existing->meta['payment_url'])) {
            return [
                'payment'     => $existing,
                'payment_url' => (string) $existing->meta['payment_url'],
                'bill_code'   => (string) ($existing->meta['slug'] ?? $existing->gateway_ref),
                'reused'      => true,
            ];
        }

        $client = SecurePayClient::forTenant($booking->tenant_id);

        return DB::transaction(function () use ($booking, $type, $amount, $client) {
            $payment = Payment::create([
                'tenant_id'       => $booking->tenant_id,
                'public_id'       => (string) Str::ulid(),
                'booking_id'      => $booking->id,
                'type'            => $type,
                'method'          => Payment::METHOD_SECUREPAY,
                'gateway_provider'=> 'securepay',
                'currency'        => $booking->currency ?? 'MYR',
                'amount'          => $amount,
                'gateway_fee'     => 0,
                'platform_fee'    => 0,
                'net_to_tenant'   => $amount,
                'status'          => Payment::STATUS_PENDING,
            ]);

            $returnUrl   = route('payments.return', ['payment' => $payment->public_id]);
            $callbackUrl = route('webhooks.securepay');

            try {
                $result = $client->createPayment(
                    $booking->fresh(['property', 'guest', 'bookingGuests']),
                    $payment,
                    $returnUrl,
                    $callbackUrl,
                );
            } catch (SecurePayException $e) {
                SecurePayLog::recordApiCall(
                    $booking->tenant_id, $payment, 'createPayment',
                    ['type' => $type, 'amount' => $amount],
                    $e->apiResponse, $e->httpStatus, false, $e->getMessage(),
                );
                throw $e;
            }

            SecurePayLog::recordApiCall(
                $booking->tenant_id, $payment, 'createPayment',
                $result['request'], $result['response'], $result['http_status'], true,
            );

            $payment->update([
                'gateway_ref' => $result['order_number'],
                'status'      => Payment::STATUS_PROCESSING,
                'meta'        => array_merge($payment->meta ?? [], [
                    'slug'           => $result['bill_code'],
                    'order_number'   => $result['order_number'],
                    'payment_url'    => $result['payment_url'],
                    'created_via'    => 'tenant_dashboard',
                    'created_by_app' => 'tempahlah',
                ]),
            ]);

            return [
                'payment'     => $payment,
                'payment_url' => $result['payment_url'],
                'bill_code'   => $result['bill_code'],
                'reused'      => false,
            ];
        });
    }
}
