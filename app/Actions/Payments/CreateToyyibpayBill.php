<?php

namespace App\Actions\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayException;
use App\Services\Payments\Toyyibpay\ToyyibpayLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create (or reuse) a Toyyibpay bill for a booking and return the payment URL.
 *
 * Reuse rule: if there's already a `processing` Payment row of the same type
 * with a `gateway_ref` (BillCode) for this booking, we hand back the existing
 * URL instead of double-billing the guest.
 */
class CreateToyyibpayBill
{
    /**
     * @param  Booking  $booking
     * @param  string   $type    Payment::TYPE_DEPOSIT | TYPE_BALANCE | TYPE_FULL
     * @param  float    $amount  In RM (will be converted to cents downstream)
     * @return array{payment: Payment, payment_url: string, bill_code: string, reused: bool}
     */
    public function execute(Booking $booking, string $type, float $amount): array
    {
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', $type)
            ->where('gateway_provider', 'toyyibpay')
            ->where('status', Payment::STATUS_PROCESSING)
            ->whereNotNull('gateway_ref')
            ->first();

        if ($existing) {
            $client = ToyyibpayClient::forTenant($booking->tenant_id);
            return [
                'payment'     => $existing,
                'payment_url' => $client->baseUrl.'/'.$existing->gateway_ref,
                'bill_code'   => (string) $existing->gateway_ref,
                'reused'      => true,
            ];
        }

        $client = ToyyibpayClient::forTenant($booking->tenant_id);

        return DB::transaction(function () use ($booking, $type, $amount, $client) {
            $payment = Payment::create([
                'tenant_id'       => $booking->tenant_id,
                'public_id'       => (string) Str::ulid(),
                'booking_id'      => $booking->id,
                'type'            => $type,
                'method'          => Payment::METHOD_TOYYIBPAY,
                'gateway_provider'=> 'toyyibpay',
                'currency'        => $booking->currency ?? 'MYR',
                'amount'          => $amount,
                'gateway_fee'     => 0,
                'platform_fee'    => 0,
                'net_to_tenant'   => $amount,
                'status'          => Payment::STATUS_PENDING,
            ]);

            $returnUrl   = route('payments.return', ['payment' => $payment->public_id]);
            $callbackUrl = route('webhooks.toyyibpay');

            try {
                $result = $client->createBill($booking->fresh(['property', 'guest', 'bookingGuests']), $payment, $returnUrl, $callbackUrl);
            } catch (ToyyibpayException $e) {
                ToyyibpayLog::recordApiCall(
                    $booking->tenant_id, $payment, 'createBill',
                    ['type' => $type, 'amount' => $amount],
                    $e->apiResponse, $e->httpStatus, false, $e->getMessage(),
                );
                throw $e;
            }

            ToyyibpayLog::recordApiCall(
                $booking->tenant_id, $payment, 'createBill',
                $result['request'], $result['response'], $result['http_status'], true,
            );

            $payment->update([
                'gateway_ref' => $result['bill_code'],
                'status'      => Payment::STATUS_PROCESSING,
                'meta'        => array_merge($payment->meta ?? [], [
                    'bill_code'      => $result['bill_code'],
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
