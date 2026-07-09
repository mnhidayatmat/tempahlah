<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Booking acknowledgement for tenants without the invoice_documents feature.
 *
 * The paid tier sends BookingInvoiceMail, which carries a numbered Invoice and
 * its PDF. Free tenants issue no document, so their guest gets this instead: the
 * booking summary plus the host's manual payment instructions. Without it a free
 * tenant's guest would receive nothing at all after booking, and would have no
 * way to learn where to send the money.
 *
 * Deliberately has no attachments() — there is no document to attach.
 */
class BookingReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking received: :ref', ['ref' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.received',
            with: [
                'booking' => $this->booking,
                'manualInstructions' => $this->booking->tenant?->manualPaymentInstructions(),
            ],
        );
    }
}
