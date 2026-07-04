<?php

namespace App\Services\Payments;

/**
 * Marker interface implemented by every payment-gateway exception
 * (ToyyibpayException, BillplzException). Lets gateway-agnostic callers —
 * the public booking flow, the manual pay-link, the payment-lifecycle
 * command — catch "any gateway failed" without knowing which gateway the
 * tenant is on.
 */
interface PaymentGatewayException
{
}
