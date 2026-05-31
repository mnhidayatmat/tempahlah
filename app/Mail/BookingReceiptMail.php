<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Payment receipt email — sent after the Toyyibpay webhook confirms a
 * deposit or full payment. Carries the formal receipt PDF as an
 * attachment, with stay details + receipt number in the body.
 */
class BookingReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Invoice $receipt,
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Receipt :num — Booking :ref', [
                'num' => $this->receipt->invoice_number,
                'ref' => $this->booking->reference,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.receipt',
            with: [
                'booking' => $this->booking,
                'receipt' => $this->receipt,
                'payment' => $this->payment,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->receipt->pdf_path) {
            return [];
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($this->receipt->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $disk->get($this->receipt->pdf_path),
                $this->receipt->invoice_number.'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
