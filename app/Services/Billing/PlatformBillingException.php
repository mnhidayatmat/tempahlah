<?php

namespace App\Services\Billing;

use RuntimeException;

class PlatformBillingException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(
            'Platform subscription billing is not configured. Set BILLPLZ_API_KEY and '
            .'BILLPLZ_COLLECTION_ID (Tempahlah\'s own Billplz merchant account) in .env.'
        );
    }
}
