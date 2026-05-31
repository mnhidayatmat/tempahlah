<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Pay-link invoice email — sent right after a public booking is created
 * on the tenant subdomain. Carries the Toyyibpay deposit link in the body
 * AND the formal invoice PDF as an attachment.
 */
class BookingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public Invoice $invoice,
        public string $payUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Pay deposit: :ref', ['ref' => $this->booking->reference]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.invoice',
            with: [
                'booking' => $this->booking,
                'invoice' => $this->invoice,
                'payUrl'  => $this->payUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->invoice->pdf_path) {
            return [];
        }

        // Pull the PDF bytes from whatever disk it was written to (Spaces
        // in prod, local in dev). Avoid relying on a public URL — invoice
        // PDFs hold PII and should not be exposed by URL.
        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($this->invoice->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => $disk->get($this->invoice->pdf_path),
                $this->invoice->invoice_number.'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
