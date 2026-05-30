<?php

namespace App\Services\WhatsApp\Sidecar;

use RuntimeException;

class SidecarException extends RuntimeException
{
    public ?int $retryAfterMs = null;

    public static function notConnected(): self
    {
        return new self('Sidecar session is not connected');
    }

    public static function rateLimited(int $retryAfterMs): self
    {
        $e = new self("Sidecar rate-limited (retry after {$retryAfterMs}ms)");
        $e->retryAfterMs = $retryAfterMs;
        return $e;
    }
}
