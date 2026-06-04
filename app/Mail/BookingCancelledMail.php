<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Booking cancellation notice — sent when a booking is cancelled, most often
 * by the auto-cancel lifecycle (unpaid booking fee or unpaid balance), but
 * also reusable for host-initiated cancellations.
 */
class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking :ref cancelled', ['ref' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.cancelled',
            with: [
                'booking' => $this->booking,
                'reason'  => $this->reason,
            ],
        );
    }
}
