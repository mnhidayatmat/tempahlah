<?php

namespace App\Mail;

use App\Models\OnboardingEmail;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * One onboarding-series step to one host. Same Markdown body + tokens +
 * signed-unsubscribe footer as campaign mail (shares the same template).
 * Tokens: {name} {business_name} {upgrade_url} {booking_url}.
 */
class OnboardingEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OnboardingEmail $step,
        public Tenant $tenant,
        public string $recipientName,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[TEST] ' : '').$this->personalize($this->step->subject),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.marketing.campaign',
            with: [
                'bodyMarkdown' => $this->personalize($this->step->body_md),
                'unsubscribeUrl' => URL::signedRoute('marketing.unsubscribe', ['tenant' => $this->tenant->id]),
                'isTest' => $this->isTest,
            ],
        );
    }

    protected function personalize(string $text): string
    {
        return strtr($text, [
            '{name}' => $this->recipientName,
            '{business_name}' => $this->tenant->business_name,
            '{upgrade_url}' => rtrim((string) config('app.url'), '/').'/dashboard/subscription',
            '{booking_url}' => $this->tenant->publicUrl(),
        ]);
    }
}
