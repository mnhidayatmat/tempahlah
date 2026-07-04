<?php

namespace App\Actions\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\Billplz\BillplzException;
use App\Services\Payments\Billplz\BillplzLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create (or reuse) a Billplz bill for a booking and return the payment URL.
 * The Billplz twin of CreateToyyibpayBill — same return contract so a gateway
 * dispatcher can treat them interchangeably.
 *
 * Reuse rule: if there's already a `processing` Payment row of the same type
 * with a `gateway_ref` (bill id) for this booking, hand back the stored URL
 * instead of double-billing the guest.
 *
 * @see CreateToyyibpayBill
 */
class CreateBillplzBill
{
    /**
     * @param  Booking  $booking
     * @param  string   $type    Payment::TYPE_DEPOSIT | TYPE_BALANCE | TYPE_FULL
     * @param  float    $amount  In RM (converted to cents downstream)
     * @return array{payment: Payment, payment_url: string, bill_code: string, reused: bool}
     */
    public function execute(Booking $booking, string $type, float $amount): array
    {
        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('type', $type)
            ->where('gateway_provider', 'billplz')
            ->where('status', Payment::STATUS_PROCESSING)
            ->whereNotNull('gateway_ref')
            ->first();

        if ($existing && ! empty($existing->meta['payment_url'])) {
            return [
                'payment'     => $existing,
                'payment_url' => (string) $existing->meta['payment_url'],
                'bill_code'   => (string) $existing->gateway_ref,
                'reused'      => true,
            ];
        }

        $client = BillplzClient::forTenant($booking->tenant_id);

        return DB::transaction(function () use ($booking, $type, $amount, $client) {
            $payment = Payment::create([
                'tenant_id'       => $booking->tenant_id,
                'public_id'       => (string) Str::ulid(),
                'booking_id'      => $booking->id,
                'type'            => $type,
                'method'          => Payment::METHOD_BILLPLZ,
                'gateway_provider'=> 'billplz',
                'currency'        => $booking->currency ?? 'MYR',
                'amount'          => $amount,
                'gateway_fee'     => 0,
                'platform_fee'    => 0,
                'net_to_tenant'   => $amount,
                'status'          => Payment::STATUS_PENDING,
            ]);

            $returnUrl   = route('payments.return', ['payment' => $payment->public_id]);
            $callbackUrl = route('webhooks.billplz');

            try {
                $result = $client->createBill(
                    $booking->fresh(['property', 'guest', 'bookingGuests']),
                    $payment,
                    $returnUrl,
                    $callbackUrl,
                );
            } catch (BillplzException $e) {
                BillplzLog::recordApiCall(
                    $booking->tenant_id, $payment, 'createBill',
                    ['type' => $type, 'amount' => $amount],
                    $e->apiResponse, $e->httpStatus, false, $e->getMessage(),
                );
                throw $e;
            }

            BillplzLog::recordApiCall(
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
