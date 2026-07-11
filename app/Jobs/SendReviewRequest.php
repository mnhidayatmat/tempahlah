<?php

namespace App\Jobs;

use App\Mail\ReviewRequestMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the post-checkout "leave a testimonial" request over email + WhatsApp.
 * Dispatched automatically when a booking is checked out (once), and manually
 * from the booking page's "Request testimonial" button.
 *
 * The signed review link is minted here (Booking::reviewUrl) so the same URL
 * goes to both channels. Stamps review_requested_at so an auto-send never
 * fires twice for one checkout.
 */
class SendReviewRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest', 'review'])
            ->findOrFail($this->bookingId);

        // Nothing to ask for if they've already left one.
        if ($booking->review) {
            return;
        }

        $url = $booking->reviewUrl();

        // Email arm — isolated so an SES rejection can't abort the WhatsApp arm.
        // (Guest email currently only lands once SES leaves sandbox; WhatsApp is
        // the channel that actually reaches guests today.)
        $email = $booking->guestEmail();
        if ($email) {
            try {
                Mail::to($email)->send(new ReviewRequestMail($booking, $url));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // WhatsApp arm — Messenger handles all gating (connected session,
        // auto_review pref, guest opt-out, recipient guard).
        try {
            WhatsappMessenger::dispatchReviewRequest($booking, $url);
        } catch (\Throwable $e) {
            report($e);
        }

        $booking->forceFill(['review_requested_at' => now()])->save();
    }
}
