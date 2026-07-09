<?php

namespace App\Mail;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The RM 49/mo bill Tempahlah sends a tenant — and, with $dunning, the reminder
 * that it's still unpaid and their Pro features are about to switch off.
 */
class SubscriptionInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionInvoice $invoice,
        public string $payUrl,
        public bool $dunning = false,
        public ?string $graceEndsOn = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dunning
                ? __('Action needed: your Tempahlah Pro subscription is unpaid')
                : __('Your Tempahlah Pro invoice :num', ['num' => $this->invoice->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription.invoice',
            with: [
                'invoice' => $this->invoice,
                'payUrl' => $this->payUrl,
                'dunning' => $this->dunning,
                'graceEndsOn' => $this->graceEndsOn,
            ],
        );
    }
}
