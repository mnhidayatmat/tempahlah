<?php

namespace App\Services\Billing;

use App\Services\Payments\PaymentGatewayException;
use RuntimeException;
use Throwable;

/**
 * Stripe platform-billing failure. Implements the shared PaymentGatewayException
 * marker so existing `catch (PaymentGatewayException)` sites handle it too.
 */
class StripeException extends RuntimeException implements PaymentGatewayException
{
    public function __construct(
        string $message,
        public readonly ?array $apiResponse = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('Stripe billing is not configured. Set STRIPE_SECRET_KEY and STRIPE_PRICE_ID in .env.');
    }

    public static function apiError(string $reason, ?array $response = null, ?int $httpStatus = null): self
    {
        return new self("Stripe API: {$reason}", $response, $httpStatus);
    }
}
