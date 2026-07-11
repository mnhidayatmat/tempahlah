<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Post-checkout "leave a testimonial" email. Carries the signed review link
 * (minted by the caller, since only the queued job has the tenant slug context).
 * From Tempahlah on the host's behalf — the same mail identity every other
 * guest email uses.
 */
class ReviewRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('How was your stay at :property?', [
                'property' => $this->booking->property?->name ?? ($this->booking->tenant?->business_name ?? config('app.name')),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.review-request',
            with: [
                'booking'   => $this->booking,
                'reviewUrl' => $this->reviewUrl,
                'business'  => $this->booking->tenant?->business_name ?? config('app.name'),
            ],
        );
    }
}
