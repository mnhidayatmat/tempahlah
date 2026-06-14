<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettlePaymentSuccess;
use App\Models\Payment;
use App\Services\Payments\Toyyibpay\ToyyibpayClient;
use Illuminate\Http\Request;

/**
 * Lands the guest back on Tempahlah after they complete (or abandon) the
 * Toyyibpay flow. Toyyibpay POSTs `status_id`, `billcode`, `order_id`, etc.
 * as query params on this URL.
 *
 * The canonical state change normally arrives server-to-server via
 * ToyyibpayWebhookController. But that async callback can be delayed or never
 * delivered, so here we ALSO reconcile against Toyyibpay directly — if the
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

        // If the webhook hasn't settled this yet, ask Toyyibpay server-to-server
        // for the real transaction state and auto-confirm if it's paid.
        if (in_array($row->status, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING], true)
            && $row->gateway_provider === 'toyyibpay'
            && $row->gateway_ref) {
            try {
                $client = ToyyibpayClient::forTenant($row->tenant_id);
                $result = $client->getBillTransactions((string) $row->gateway_ref);

                // billpaymentStatus: "1" = success, "2" = pending, "3" = fail.
                $paid = collect($result['transactions'] ?? [])
                    ->contains(fn ($t) => (string) ($t['billpaymentStatus'] ?? '') === '1');

                if ($paid) {
                    $settle->execute($row);
                    $row->refresh()->load('booking.property', 'booking.tenant');
                }
            } catch (\Throwable $e) {
                // Couldn't reach Toyyibpay — fall through and show pending; the
                // webhook (and a page refresh) will settle it shortly.
                report($e);
            }
        }

        return view('payments.return', [
            'payment' => $row,
            'booking' => $row->booking,
            'statusId' => $request->query('status_id'),
        ]);
    }
}
