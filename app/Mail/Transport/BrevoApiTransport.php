<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's transactional HTTP API (POST /v3/smtp/email),
 * authenticated with the account's `xkeysib-…` API key.
 *
 * Hand-rolled on top of the Http facade rather than pulling in
 * symfony/brevo-mailer, because prod auto-deploy is `git reset --hard` +
 * `migrate` — it never runs `composer install`, so a new package would fatal
 * on deploy. This mirrors how every payment gateway in the app talks to its
 * provider (raw Http, no SDK).
 *
 * Registered as the `brevo` mail transport in AppServiceProvider::boot via
 * Mail::extend, so it slots in behind Laravel's normal Mailable pipeline —
 * the SES bounce/complaint suppression listener still runs unchanged.
 */
class BrevoApiTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom()[0] ?? null;

        $payload = [
            'sender'  => $this->addr($from),
            'to'      => $this->addrs($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        if ($html !== null) {
            $payload['htmlContent'] = $html;
        }
        if ($text !== null) {
            $payload['textContent'] = $text;
        }
        // Brevo requires at least one body — guard against a bodyless message.
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = ' ';
        }

        if ($cc = $this->addrs($email->getCc())) {
            $payload['cc'] = $cc;
        }
        if ($bcc = $this->addrs($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }
        if ($reply = $email->getReplyTo()[0] ?? null) {
            $payload['replyTo'] = $this->addr($reply);
        }

        // Attachments (e.g. invoice / receipt PDFs) as base64.
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $filename = $part->getFilename()
                ?? ($part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment');
            $attachments[] = [
                'name'    => $filename,
                'content' => base64_encode($part->getBody()),
            ];
        }
        if ($attachments) {
            $payload['attachment'] = $attachments;
        }

        $response = Http::withHeaders([
            'api-key'      => $this->apiKey,
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ])->timeout(30)->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            // Bubble up so the mail layer logs/reports it (callers already wrap
            // Mail sends in try/catch, so this never aborts a booking flow).
            throw new \RuntimeException(
                'Brevo API send failed ('.$response->status().'): '.$response->body()
            );
        }
    }

    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }

    /** @return array{email:string,name?:string} */
    private function addr(?Address $address): array
    {
        if (! $address) {
            return [];
        }
        $out = ['email' => $address->getAddress()];
        if ($address->getName() !== '') {
            $out['name'] = $address->getName();
        }

        return $out;
    }

    /** @param Address[] $addresses */
    private function addrs(array $addresses): array
    {
        return array_values(array_map(fn (Address $a) => $this->addr($a), $addresses));
    }
}
