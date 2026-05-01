<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        protected ?string $phoneNumberId,
        protected ?string $accessToken,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            env('WHATSAPP_PHONE_NUMBER_ID'),
            env('WHATSAPP_ACCESS_TOKEN'),
        );
    }

    public function clickToChatLink(string $phone, string $message): string
    {
        return 'https://wa.me/'.preg_replace('/[^0-9]/', '', $phone).'?text='.urlencode($message);
    }

    public function sendTemplate(string $to, string $templateName, array $params = []): array
    {
        if (! $this->phoneNumberId || ! $this->accessToken) {
            Log::info('WhatsApp Business API not configured — skipping send', compact('to', 'templateName'));
            return ['skipped' => true];
        }

        $response = Http::withToken($this->accessToken)
            ->post("https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'ms'],
                    'components' => $params,
                ],
            ])
            ->throw()
            ->json();

        return $response;
    }
}
