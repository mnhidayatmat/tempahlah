<?php

namespace App\Mail;

use App\Models\MarketingCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * One campaign email to one host. The admin writes the body in Markdown; it is
 * embedded verbatim into the markdown mail template (so the normal mail
 * pipeline renders it), after the personalization tokens are substituted:
 *   {name}           the recipient's name (owner name / business name)
 *   {business_name}  the tenant's business name
 *   {upgrade_url}    the in-app subscription page
 * Every send carries a signed unsubscribe link (PDPA) in the footer.
 */
class MarketingCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MarketingCampaign $campaign,
        public string $recipientName,
        public string $businessName,
        public int $tenantId,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[TEST] ' : '').$this->personalize($this->campaign->subject),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.marketing.campaign',
            with: [
                'bodyMarkdown' => $this->personalize($this->campaign->body_md),
                'unsubscribeUrl' => URL::signedRoute('marketing.unsubscribe', ['tenant' => $this->tenantId]),
                'isTest' => $this->isTest,
            ],
        );
    }

    protected function personalize(string $text): string
    {
        return strtr($text, [
            '{name}' => $this->recipientName,
            '{business_name}' => $this->businessName,
            '{upgrade_url}' => rtrim((string) config('app.url'), '/').'/dashboard/subscription',
        ]);
    }
}
