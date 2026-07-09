<?php

namespace App\Services\Payments\SecurePay;

use App\Services\Payments\PaymentGatewayException;
use RuntimeException;
use Throwable;

class SecurePayException extends RuntimeException implements PaymentGatewayException
{
    public ?array $apiResponse = null;
    public ?int $httpStatus = null;

    public static function notConfigured(int $tenantId): self
    {
        return new self("Tenant {$tenantId} has no SecurePay integration configured");
    }

    public static function apiError(string $reason, ?array $response = null, ?int $httpStatus = null, ?Throwable $prev = null): self
    {
        $e = new self("SecurePay API: {$reason}", 0, $prev);
        $e->apiResponse = $response;
        $e->httpStatus = $httpStatus;
        return $e;
    }

    public static function invalidSignature(string $detail = ''): self
    {
        return new self('SecurePay callback checksum invalid'.($detail ? " ({$detail})" : ''));
    }
}
