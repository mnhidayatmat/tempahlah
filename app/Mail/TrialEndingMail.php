<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The "your free Pro trial is ending — want to keep Pro?" nudge, sent a few
 * days before a card-free signup trial lapses back to Free.
 */
class TrialEndingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public string $subscribeUrl,
        public string $endsOn,
        public int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your Tempahlah Pro trial ends in :days day(s)', ['days' => $this->daysLeft]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.subscription.trial-ending',
            with: [
                'businessName' => $this->subscription->tenant?->business_name,
                'subscribeUrl' => $this->subscribeUrl,
                'endsOn' => $this->endsOn,
                'daysLeft' => $this->daysLeft,
            ],
        );
    }
}
