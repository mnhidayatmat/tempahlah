<?php

namespace App\Services\WhatsApp;

/**
 * Malaysian-friendly E.164 normalizer.
 *
 * Inputs we accept (real-world tenant data):
 *   "012-345 6789"   → +60123456789
 *   "0123456789"     → +60123456789
 *   "60123456789"    → +60123456789
 *   "+60123456789"   → +60123456789
 *   "+65 9123 4567"  → +6591234567
 *   "+1 (415)..."    → +1415...
 *
 * Anything we can't confidently interpret returns null — callers MUST treat
 * null as "do not send" rather than "send to a guessed number".
 */
class PhoneNumber
{
    /**
     * Normalize a phone string to E.164 (digits only, with leading +).
     */
    public static function normalize(?string $raw, string $defaultCountry = 'MY'): ?string
    {
        if (! $raw) return null;

        // Strip spaces, dashes, parens, dots.
        $cleaned = preg_replace('/[^\d+]/', '', $raw);
        if (! $cleaned) return null;

        // Already in international form.
        if (str_starts_with($cleaned, '+')) {
            $digits = substr($cleaned, 1);
            return strlen($digits) >= 8 && strlen($digits) <= 15
                ? '+'.$digits
                : null;
        }

        // Local Malaysian (starts with 0): strip 0, prepend 60.
        if ($defaultCountry === 'MY' && str_starts_with($cleaned, '0')) {
            $digits = '60'.substr($cleaned, 1);
            return strlen($digits) >= 10 && strlen($digits) <= 13
                ? '+'.$digits
                : null;
        }

        // Already country-coded without +.
        if (strlen($cleaned) >= 10 && strlen($cleaned) <= 15) {
            return '+'.$cleaned;
        }

        return null;
    }

    /**
     * True if both inputs normalize to the same E.164 number.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);
        return $na !== null && $na === $nb;
    }
}
