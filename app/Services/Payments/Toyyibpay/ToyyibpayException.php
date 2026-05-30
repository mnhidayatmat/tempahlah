<?php

namespace App\Services\Payments\Toyyibpay;

use RuntimeException;
use Throwable;

class ToyyibpayException extends RuntimeException
{
    public ?array $apiResponse = null;
    public ?int $httpStatus = null;

    public static function notConfigured(int $tenantId): self
    {
        return new self("Tenant {$tenantId} has no Toyyibpay integration configured");
    }

    public static function apiError(string $reason, ?array $response = null, ?int $httpStatus = null, ?Throwable $prev = null): self
    {
        $e = new self("Toyyibpay API: {$reason}", 0, $prev);
        $e->apiResponse = $response;
        $e->httpStatus = $httpStatus;
        return $e;
    }

    public static function invalidSignature(string $detail = ''): self
    {
        return new self('Toyyibpay callback signature invalid'.($detail ? " ({$detail})" : ''));
    }
}
