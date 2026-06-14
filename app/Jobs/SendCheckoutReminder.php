<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the pre-checkout WhatsApp reminder (host's checkout guidelines) for a
 * single booking. Dispatched by the scheduled DispatchCheckoutReminders
 * command. Stamps bookings.checkout_reminder_sent_at so it fires at most once.
 */
class SendCheckoutReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with('property', 'tenant', 'guest')
            ->find($this->bookingId);

        if (! $booking) {
            return;
        }
        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW, Booking::STATUS_CHECKED_OUT], true)) {
            return;
        }
        // Re-check the tenant toggle at send time so a host who turned it off
        // between the command run and this job doesn't get a stray send.
        if (! $booking->tenant?->checkoutReminderEnabled()) {
            return;
        }

        WhatsappMessenger::dispatchCheckoutReminder($booking);

        $booking->forceFill(['checkout_reminder_sent_at' => now()])->saveQuietly();
    }
}
