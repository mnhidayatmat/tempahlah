<?php

namespace App\Actions\Booking;

use App\Jobs\PushBookingToGoogleCalendar;
use App\Jobs\SendBookingCancellation;
use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a booking (soft — flips status, keeps the row for the audit trail).
 *
 * Single source of truth shared by the host's manual cancel
 * (BookingController::cancel) and the automated payment-lifecycle
 * auto-cancel (unpaid booking fee / unpaid balance). Both should:
 *   - flip status → cancelled, freeing the dates back to availability;
 *   - cancel any not-yet-started cleaning/laundry tasks;
 *   - remove the event from the tenant's Google Calendar (if it was pushed);
 *   - notify the guest by email + WhatsApp.
 *
 * No refund is auto-issued — the booking fee is non-refundable by policy,
 * and any goodwill refund is arranged by the host out-of-band.
 *
 * Returns true if it cancelled, false if the booking was already
 * cancelled / no-show / checked-out (no-op).
 */
class CancelBooking
{
    public function execute(Booking $booking, ?string $reason = null, bool $notifyGuest = true): bool
    {
        if (in_array($booking->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_NO_SHOW,
            Booking::STATUS_CHECKED_OUT,
        ], true)) {
            return false;
        }

        DB::transaction(function () use ($booking, $reason) {
            $booking->update([
                'status'              => Booking::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ]);

            CleaningTask::where('booking_id', $booking->id)
                ->whereIn('status', ['pending', 'scheduled'])
                ->update(['status' => 'cancelled']);

            LaundryTask::where('booking_id', $booking->id)
                ->whereIn('status', ['pending', 'scheduled'])
                ->update(['status' => 'cancelled']);
        });

        // Smart-sync job detects status=cancelled + google_event_id → DELETE.
        // No-ops when the tenant hasn't connected Google Calendar.
        PushBookingToGoogleCalendar::dispatch($booking->id);

        if ($notifyGuest) {
            SendBookingCancellation::dispatch($booking->id, $reason);
        }

        return true;
    }
}
