<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Lands the guest back on Tempahlah after they complete (or abandon) the
 * Toyyibpay flow. Toyyibpay POSTs `status_id`, `billcode`, `order_id`, etc.
 * as query params on this URL.
 *
 * This is a UX-only landing page — the canonical state change happens
 * server-to-server via ToyyibpayWebhookController. We just show the guest
 * a friendly status until the webhook arrives.
 */
class PaymentReturnController extends Controller
{
    public function show(Request $request, string $payment)
    {
        $row = Payment::withoutGlobalScopes()
            ->with('booking.property', 'booking.tenant')
            ->where('public_id', $payment)
            ->firstOrFail();

        // Trust nothing in the URL — read the canonical status from our DB.
        // If the webhook hasn't arrived yet the guest will see `pending`
        // and a refresh after a few seconds will flip to succeeded/failed.
        return view('payments.return', [
            'payment' => $row,
            'booking' => $row->booking,
            'statusId' => $request->query('status_id'),
        ]);
    }
}
