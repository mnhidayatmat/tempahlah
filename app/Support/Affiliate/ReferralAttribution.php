<?php

namespace App\Support\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

/**
 * Referral attribution for the affiliate program. Mirrors
 * Support\Marketplace\Attribution but is COOKIE-based, not session-based: the
 * referral has to survive the Google OAuth round-trip and days of
 * consideration before the prospect actually registers, so a 60-day cookie
 * (last click wins) is the carrier. CreateTenantAndOwner reads it back when
 * the tenant is created.
 */
class ReferralAttribution
{
    public const COOKIE = 'tph_ref';

    /**
     * Capture ?ref={code} into the cookie (last click wins) and bump the
     * affiliate's daily click counter. No-ops on junk codes so a mistyped
     * link can never break a page load.
     */
    public static function capture(Request $request): void
    {
        $code = self::sanitize((string) $request->query('ref', ''));

        if ($code === '') {
            return;
        }

        try {
            $affiliate = Affiliate::query()
                ->where('code', $code)
                ->where('status', Affiliate::STATUS_ACTIVE)
                ->first();

            if (! $affiliate) {
                return;
            }

            Cookie::queue(cookie(self::COOKIE, $affiliate->code, self::cookieDays() * 24 * 60));

            self::countClick($affiliate->id);
        } catch (\Throwable $e) {
            // Attribution is best-effort — never let it break the request
            // (e.g. mid-deploy before the affiliate tables exist).
            Log::warning('Referral capture failed', ['code' => $code, 'error' => $e->getMessage()]);
        }
    }

    /** The referral code carried by this request's cookie, or null. */
    public static function code(Request $request): ?string
    {
        $code = self::sanitize((string) $request->cookie(self::COOKIE, ''));

        return $code !== '' ? $code : null;
    }

    /** Clear the cookie — call after a signup has consumed it. */
    public static function clear(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function cookieDays(): int
    {
        return max(1, (int) config('homestay.affiliate.cookie_days', 60));
    }

    /** Codes are short alphanumerics — anything else is junk. */
    protected static function sanitize(string $raw): string
    {
        $raw = strtoupper(trim($raw));

        return preg_match('/^[A-Z0-9\-]{3,24}$/', $raw) ? $raw : '';
    }

    protected static function countClick(int $affiliateId): void
    {
        $visit = AffiliateVisit::query()->firstOrCreate(
            ['affiliate_id' => $affiliateId, 'date' => now()->toDateString()],
            ['clicks' => 0],
        );

        $visit->increment('clicks');
    }
}
