<?php

namespace App\Mail;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emails the guest a secure link to submit their bank account so the host
 * can transfer the deposit refund. Takes the refund id (not the model) so
 * the queued job re-loads a fresh row + re-mints the signed link at send
 * time — a signed URL captured at queue time could otherwise expire.
 */
class RefundBankRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public int $refundId) {}

    public function envelope(): Envelope
    {
        $refund = Refund::with('booking.tenant')->find($this->refundId);
        $business = $refund?->booking?->tenant?->business_name ?? config('app.name');

        return new Envelope(
            subject: __(':business — your deposit refund', ['business' => $business]),
        );
    }

    public function content(): Content
    {
        $refund = Refund::with('booking.tenant')->findOrFail($this->refundId);

        return new Content(
            markdown: 'emails.refunds.bank-request',
            with: [
                'refund'   => $refund,
                'booking'  => $refund->booking,
                'tenant'   => $refund->booking?->tenant,
                'url'      => $refund->bankFormUrl(),
                'amount'   => number_format((float) $refund->amount, 2),
            ],
        );
    }
}
