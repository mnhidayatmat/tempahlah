<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettlePaymentSuccess;
use App\Models\Payment;
use App\Services\Payments\Billplz\BillplzClient;
use App\Services\Payments\SecurePay\SecurePayClient;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use Illuminate\Http\Request;

/**
 * Lands the guest back on Tempahlah after they complete (or abandon) a gateway
 * payment flow (Toyyibpay, Billplz or SecurePay).
 *
 * Reached by GET for Toyyibpay/Billplz and by POST for SecurePay, which posts
 * its result to `redirect_url`. The posted body is never trusted.
 *
 * The canonical state change normally arrives server-to-server via the
 * gateway's webhook. But that async callback can be delayed or never
 * delivered, so here we ALSO reconcile against the gateway directly — if the
 * bill is actually paid, the booking is confirmed right away (same shared
 * SettlePaymentSuccess action the webhook uses) instead of sitting on
 * "pending" until a human marks it. URL params are never trusted.
 */
class PaymentReturnController extends Controller
{
    public function show(Request $request, string $payment, SettlePaymentSuccess $settle)
    {
        $row = Payment::withoutGlobalScopes()
            ->with('booking.property', 'booking.tenant')
            ->where('public_id', $payment)
            ->firstOrFail();

        // If the webhook hasn't settled this yet, ask the gateway
        // server-to-server for the real state and auto-confirm if it's paid.
        if (in_array($row->status, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING], true)
            && $row->gateway_ref) {
            try {
                if ($this->reconcile($row)) {
                    $settle->execute($row);
                    $row->refresh()->load('booking.property', 'booking.tenant');
                }
            } catch (\Throwable $e) {
                // Couldn't reach the gateway — fall through and show pending;
                // the webhook (and a page refresh) will settle it shortly.
                report($e);
            }
        }

        return view('payments.return', [
            'payment' => $row,
            'booking' => $row->booking,
            'statusId' => $request->query('status_id'),
        ]);
    }

    /**
     * Ask the gateway whether this bill is actually paid. Returns true only on
     * a confirmed server-side paid state.
     */
    protected function reconcile(Payment $row): bool
    {
        if ($row->gateway_provider === 'toyyibpay') {
            $client = ToyyibpayClient::forTenant($row->tenant_id);
            $result = $client->getBillTransactions((string) $row->gateway_ref);

            // billpaymentStatus: "1" = success, "2" = pending, "3" = fail.
            return collect($result['transactions'] ?? [])
                ->contains(fn ($t) => (string) ($t['billpaymentStatus'] ?? '') === '1');
        }

        if ($row->gateway_provider === 'billplz') {
            $client = BillplzClient::forTenant($row->tenant_id);
            return (bool) $client->getBill((string) $row->gateway_ref)['paid'];
        }

        if ($row->gateway_provider === 'securepay') {
            // gateway_ref holds the order_number we sent (= payment public_id).
            $client = SecurePayClient::forTenant($row->tenant_id);
            return (bool) $client->getPaymentStatus((string) $row->gateway_ref)['paid'];
        }

        return false;
    }
}
