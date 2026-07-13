<?php

namespace App\Jobs;

use App\Mail\ReviewRequestMail;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the post-checkout "leave a testimonial" request by EMAIL only.
 * Dispatched automatically whenever a booking becomes checked-out — via the
 * manual "Check out guest" button, the 24h auto-checkout command, or a status
 * change to "Checked out" from the inline dropdown / edit form — and manually
 * from the booking page's "Request testimonial" button.
 *
 * ONCE-ONLY guarantee: the automatic path atomically claims review_requested_at
 * with a single conditional UPDATE, so even if several of those triggers fire
 * for the same booking, only the first one to run actually sends — the guest
 * never gets a duplicate testimonial email. The manual button passes force=true
 * to deliberately re-send (a no-op once the guest has reviewed).
 *
 * The signed review link is minted here (Booking::reviewUrl). Testimonials are
 * intentionally email-only (host preference); the WhatsApp arm was removed.
 */
class SendReviewRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId, public bool $force = false)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        // Atomic once-only claim for automatic sends. whereNull(...) + update()
        // is a single UPDATE, so of any number of concurrent/duplicate auto
        // dispatches exactly one gets a non-zero affected-row count and proceeds;
        // the rest see review_requested_at already set and bail here. This is
        // what guarantees the guest is asked at most once.
        if (! $this->force) {
            $claimed = Booking::withoutGlobalScopes()
                ->whereKey($this->bookingId)
                ->whereNull('review_requested_at')
                ->update(['review_requested_at' => now()]);

            if ($claimed === 0) {
                return; // another dispatch already claimed (or sent) this one
            }
        }

        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest', 'review'])
            ->find($this->bookingId);

        if (! $booking) {
            return;
        }

        // Nothing to ask for if they've already left one.
        if ($booking->review) {
            return;
        }

        $url = $booking->reviewUrl();

        // Email only. Wrapped in try/catch so a delivery hiccup can't bubble up
        // and fail the queued job (which would leave review_requested_at stamped
        // but retry uselessly). A guest with no email on file simply isn't asked.
        $email = $booking->guestEmail();
        if ($email) {
            try {
                Mail::to($email)->send(new ReviewRequestMail($booking, $url));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // For the force path (manual re-send) the flag may still be null on the
        // very first send — stamp it so the automatic path treats it as done.
        if (! $booking->review_requested_at) {
            $booking->forceFill(['review_requested_at' => now()])->save();
        }
    }
}
