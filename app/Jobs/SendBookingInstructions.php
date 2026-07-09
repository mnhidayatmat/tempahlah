<?php

namespace App\Jobs;

use App\Mail\BookingReceivedMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Free-tier twin of SendBookingInvoice: email + WhatsApp the booking summary and
 * the host's payment instructions, with no Invoice record and no PDF.
 *
 * Issuing invoice/receipt documents is a paid feature, but the guest of a free
 * tenant still has to be told what they booked and where to send the money — so
 * this carries everything except the document.
 *
 * Mirrors SendBookingInvoice's shape: queue `email`, each arm independently
 * wrapped so a failure in one never cancels the other.
 */
class SendBookingInstructions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bookingId,
    ) {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest'])
            ->find($this->bookingId);

        if (! $booking) {
            return;
        }

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        // Email arm — only if we have a recipient address.
        if ($lead?->email) {
            try {
                Mail::to($lead->email)->send(new BookingReceivedMail($booking));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // WhatsApp arm — messenger handles all gating internally.
        try {
            WhatsappMessenger::dispatchBookingReceived($booking);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
