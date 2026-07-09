<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Thrown when a bill is requested for a tenant with no usable payment gateway —
 * either none is enabled, or the tenant is on the free plan (online gateways are
 * a paid feature; see CreateGatewayBill::gatewayAllowed).
 *
 * Implements the gateway marker interface so the existing gateway-agnostic
 * callers already catch it and fall back to their manual / WhatsApp paths.
 */
class NoGatewayAvailableException extends RuntimeException implements PaymentGatewayException
{
    public static function forTenant(int $tenantId): self
    {
        return new self("No payment gateway available for tenant {$tenantId}.");
    }
}
