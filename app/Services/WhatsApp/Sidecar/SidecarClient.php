<?php

namespace App\Services\WhatsApp\Sidecar;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the Node Baileys sidecar.
 *
 * Loopback only in production. Bearer-authenticated. Idempotent on /start
 * (sidecar will return current state if a session is already up).
 */
class SidecarClient
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $authToken,
        protected int $timeout = 8,
        protected int $sendTimeout = 30,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim(config('whatsapp.baileys.base_url'), '/'),
            config('whatsapp.baileys.auth_token'),
            (int) config('whatsapp.baileys.timeout_seconds', 8),
            (int) config('whatsapp.baileys.send_timeout_seconds', 30),
        );
    }

    public function start(int $tenantId): array
    {
        return $this->client()
            ->post("/sessions/{$tenantId}/start")
            ->throw()
            ->json();
    }

    public function status(int $tenantId): array
    {
        return $this->client()
            ->get("/sessions/{$tenantId}/status")
            ->throw()
            ->json();
    }

    /**
     * @param  array{url?:string, kind?:string, filename?:string}|null  $media
     */
    public function send(int $tenantId, string $to, string $body, ?array $media = null): array
    {
        $response = $this->client($this->sendTimeout)
            ->post("/sessions/{$tenantId}/send", array_filter([
                'to' => $to,
                'body' => $body,
                'media' => $media,
            ]));

        if ($response->status() === 429) {
            $payload = $response->json();
            throw SidecarException::rateLimited((int) ($payload['retryAfterMs'] ?? 0));
        }
        if ($response->status() === 409) {
            throw SidecarException::notConnected();
        }

        return $response->throw()->json();
    }

    public function logout(int $tenantId): void
    {
        $this->client()
            ->post("/sessions/{$tenantId}/logout")
            ->throw();
    }

    public function isReachable(): bool
    {
        try {
            return (bool) $this->client(3)
                ->get('/health')
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function client(?int $timeout = null): PendingRequest
    {
        if (! $this->authToken) {
            throw new RuntimeException('whatsapp.baileys.auth_token not configured');
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->authToken)
            ->timeout($timeout ?? $this->timeout)
            ->acceptJson()
            ->connectTimeout(3);
    }
}
