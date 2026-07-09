<?php

namespace App\Actions\Payments;

use App\Actions\Invoicing\GenerateInvoice;
use App\Jobs\PushBookingToGoogleCalendar;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingReceipt;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * Apply the canonical "payment succeeded" state change to a Payment and its
 * Booking — mark the payment succeeded, stamp the paid dates, confirm the
 * booking, then fire the confirmation + receipt comms and sync Google Calendar
 * the FIRST time the booking transitions out of pending.
 *
 * This is the single source of truth shared by:
 *   - ToyyibpayWebhookController (async billCallbackUrl), and
 *   - PaymentReturnController (server-side verify when the guest lands back),
 * so a delayed or never-delivered callback never leaves a paid booking stuck
 * on "pending" needing a manual "Mark paid".
 *
 * Idempotent + race-safe: the booking row is locked for the duration, the
 * pending→confirmed transition is re-checked under the lock, and paid dates are
 * never re-stamped — so the webhook and the return page can race without
 * double-confirming or double-sending the guest's receipt.
 */
class SettlePaymentSuccess
{
    public function execute(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Mark the payment succeeded if it isn't already. Done conditionally
            // so we never clobber meta/paid_at the webhook already wrote.
            if ($payment->status !== Payment::STATUS_SUCCEEDED) {
                $payment->update([
                    'status' => Payment::STATUS_SUCCEEDED,
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            }

            // Lock the booking so a simultaneously-arriving webhook/return-page
            // settlement can't both treat it as a fresh confirmation. No global
            // scopes — the webhook/return run without tenant context.
            $booking = $payment->booking()->withoutGlobalScopes()->lockForUpdate()->first();
            if (! $booking) {
                return;
            }

            if (in_array($payment->type, [Payment::TYPE_DEPOSIT, Payment::TYPE_FULL], true)) {
                $wasPending = $booking->status === Booking::STATUS_PENDING;

                $booking->update([
                    'deposit_paid_at' => $booking->deposit_paid_at ?? now(),
                    // A FULL payment settles the balance outright (last-minute
                    // bookings paid in full to confirm) — stamp it so the
                    // balance reminder/auto-cancel never chases a paid booking.
                    'balance_paid_at' => $payment->type === Payment::TYPE_FULL
                        ? ($booking->balance_paid_at ?? now())
                        : $booking->balance_paid_at,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);

                if ($wasPending) {
                    // 1. Warm confirmation message (email + WhatsApp).
                    SendBookingConfirmation::dispatch($booking->id);

                    // 2. Formal receipt: PDF + email + WhatsApp. Guarded so a
                    //    PDF/storage hiccup can't break the settlement.
                    $this->dispatchReceipt($booking, $payment);

                    // 3. Sync to the tenant's connected Google Calendar (no-op
                    //    when GCal isn't connected).
                    PushBookingToGoogleCalendar::dispatch($booking->id);

                    // 4. Auto-schedule housekeeping (turnover + laundry +
                    //    pre-arrival dusting) per the tenant's SOP. Guarded so a
                    //    scheduling hiccup can't break the settlement.
                    $this->generateOperationalTasks($booking);
                }
            }

            if ($payment->type === Payment::TYPE_BALANCE) {
                $alreadyPaid = $booking->balance_paid_at !== null;
                $booking->update(['balance_paid_at' => $booking->balance_paid_at ?? now()]);

                // Only receipt the first time the balance settles.
                if (! $alreadyPaid) {
                    $this->dispatchReceipt($booking, $payment);
                }
            }
        });
    }

    protected function generateOperationalTasks(Booking $booking): void
    {
        try {
            app(\App\Actions\Operations\GenerateOperationalTasksForBooking::class)
                ->execute($booking->fresh(['property']));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Receipts are a paid feature. A free tenant cannot use an online gateway at
     * all (see CreateGatewayBill::gatewayAllowed), so in practice this never
     * settles for one — but guard anyway rather than mint a document the tenant's
     * plan doesn't include.
     */
    protected function dispatchReceipt(Booking $booking, Payment $payment): void
    {
        $tenant = $booking->tenant;

        if (! $tenant || ! Feature::for($tenant)->active('invoice_documents')) {
            return;
        }

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
