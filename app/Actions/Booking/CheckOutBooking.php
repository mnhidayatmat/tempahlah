<?php

namespace App\Actions\Booking;

use App\Jobs\SendReviewRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Canonical "check the guest out" transition, shared by the manual dashboard
 * button (BookingController::checkOut) and the daily auto-checkout command
 * (AutoCheckoutBookings) so both behave identically:
 *
 *   1. status -> checked_out, stamp checked_out_at (+ checked_in_at if missing)
 *   2. prepare a pending Refund row for the deposit (the refundable security
 *      deposit the host returns after a satisfactory stay), unless one exists
 *   3. auto-request a testimonial ONCE (guarded by review_requested_at)
 *
 * Idempotent: only acts on a booking that is currently confirmed or
 * checked_in; anything else (already checked_out, cancelled, no-show) is a
 * no-op that returns false.
 */
class CheckOutBooking
{
    /** @return bool true if the booking was transitioned; false if not eligible. */
    public function execute(Booking $booking): bool
    {
        $allowedFrom = [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN];
        if (! in_array($booking->status, $allowedFrom, true)) {
            return false;
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'          => Booking::STATUS_CHECKED_OUT,
                'checked_out_at'  => now(),
                // If they never explicitly checked-in, stamp it now too so the
                // timeline isn't missing a step.
                'checked_in_at'   => $booking->checked_in_at ?? now(),
            ]);

            // Auto-create the refund record — only when a deposit was actually
            // paid AND there's no existing open/settled refund row.
            $depositPaid = (float) ($booking->deposit_amount ?? 0);
            if ($depositPaid > 0 && $booking->deposit_paid_at) {
                $hasOpen = Refund::where('booking_id', $booking->id)
                    ->whereIn('status', [
                        Refund::STATUS_PENDING,
                        Refund::STATUS_PROCESSING,
                        Refund::STATUS_COMPLETED,
                    ])->exists();

                if (! $hasOpen) {
                    $depositPayment = $booking->payments
                        ->where('status', 'succeeded')
                        ->where('type', Payment::TYPE_DEPOSIT)
                        ->first();

                    Refund::create([
                        'public_id'    => (string) Str::ulid(),
                        'tenant_id'    => $booking->tenant_id,
                        'booking_id'   => $booking->id,
                        'payment_id'   => $depositPayment?->id,
                        'amount'       => $depositPaid,
                        'currency'     => $booking->currency ?? 'MYR',
                        'reason'       => Refund::REASON_CHECKOUT_COMPLETE,
                        'status'       => Refund::STATUS_PENDING,
                        'requested_at' => now(),
                    ]);
                }
            }
        });

        // Auto-request a testimonial once, right after checkout. Guarded by
        // review_requested_at so a re-checkout (or a status flip back and
        // forth) never nags the guest twice. The job itself is a no-op if the
        // guest has already left a review.
        if (! $booking->review_requested_at) {
            SendReviewRequest::dispatch($booking->id);
        }

        return true;
    }
}
