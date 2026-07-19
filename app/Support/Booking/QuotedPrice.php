<?php

namespace App\Support\Booking;

use Illuminate\Support\Facades\Log;

/**
 * Tamper-proof "agreed price" for a sent booking-form link.
 *
 * The "Send booking form" flow builds a plain, public URL that the guest can
 * edit freely — dates, guests, everything. That is by design (the price is
 * recomputed and availability re-checked server-side on submit). But when the
 * host wants the guest to pay a SPECIFIC agreed price (a discount, a special
 * whole-house rate), a raw `?price=` param would let the guest simply lower it
 * in the URL and pay less.
 *
 * So the price rides in the link signed with an HMAC bound to the exact
 * (tenant, property, check-in, check-out, amount). The signature is generated
 * server-side (it needs APP_KEY) and re-verified server-side on submit. A guest
 * who edits the price, the dates or the property invalidates the signature, and
 * the booking falls back to auto-pricing — never silently accepting a tampered
 * number.
 *
 * The signed amount is the ACCOMMODATION SUBTOTAL (room nights, before SST /
 * tourism tax / booking fee) — identical to the dashboard manual-booking
 * "Accommodation price" override, which maps to CreateBooking's `base_amount`.
 */
class QuotedPrice
{
    /** Reject absurd values before signing/verifying. */
    public const MAX_AMOUNT = 1_000_000.0;

    /**
     * Canonical 2-decimal string for an amount, so the value that gets signed,
     * put in the URL and re-verified is byte-identical across PHP and the URL.
     */
    public static function normalizeAmount(int|float|string|null $amount): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (! is_numeric($amount)) {
            return null;
        }

        $value = round((float) $amount, 2);

        if ($value < 0 || $value > self::MAX_AMOUNT) {
            return null;
        }

        return number_format($value, 2, '.', '');
    }

    /**
     * The two host-settable amounts a "Send booking form" link can carry. The
     * purpose is folded into the signed payload so a signature minted for one
     * amount can never be replayed as the other (e.g. a low "pay-now" sig can't
     * masquerade as the stay total).
     *
     *  - PURPOSE_STAY:   the accommodation subtotal (whole-stay price) →
     *                    CreateBooking `base_amount`.
     *  - PURPOSE_PAYNOW: the amount the guest pays now (deposit / booking fee,
     *                    default RM 100) → CreateBooking `deposit_amount`. The
     *                    stay total is unchanged; only the split shifts.
     */
    public const PURPOSE_STAY = 'stay';
    public const PURPOSE_PAYNOW = 'paynow';

    public const PURPOSES = [self::PURPOSE_STAY, self::PURPOSE_PAYNOW];

    /**
     * HMAC signature over the exact stay + amount + purpose. Returns null when
     * the amount is unusable (so callers never emit an unsignable link).
     */
    public static function sign(int $tenantId, int $propertyId, ?string $checkIn, ?string $checkOut, int|float|string|null $amount, string $purpose = self::PURPOSE_STAY): ?string
    {
        $normalized = self::normalizeAmount($amount);

        if ($normalized === null || ! $checkIn || ! $checkOut || ! in_array($purpose, self::PURPOSES, true)) {
            return null;
        }

        return hash_hmac('sha256', self::payload($tenantId, $propertyId, $checkIn, $checkOut, $normalized, $purpose), self::key());
    }

    /**
     * True when $sig is a valid signature for this exact stay + amount +
     * purpose. Any mismatch (edited price, changed dates, wrong tenant, wrong
     * purpose, missing sig) → false.
     */
    public static function verify(int $tenantId, int $propertyId, ?string $checkIn, ?string $checkOut, int|float|string|null $amount, ?string $sig, string $purpose = self::PURPOSE_STAY): bool
    {
        if (! is_string($sig) || $sig === '') {
            return false;
        }

        $expected = self::sign($tenantId, $propertyId, $checkIn, $checkOut, $amount, $purpose);

        if ($expected === null) {
            return false;
        }

        return hash_equals($expected, $sig);
    }

    protected static function payload(int $tenantId, int $propertyId, string $checkIn, string $checkOut, string $amount, string $purpose): string
    {
        return implode('|', ['quoted-price', $purpose, $tenantId, $propertyId, $checkIn, $checkOut, $amount]);
    }

    protected static function key(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            // Should never happen in a booted app; log loudly rather than sign
            // with an empty secret.
            Log::error('QuotedPrice: APP_KEY is empty — cannot sign quoted prices.');
        }

        return $key;
    }
}
