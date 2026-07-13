<?php

namespace App\Http\Middleware;

use App\Support\Affiliate\ReferralAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures ?ref={affiliate_code} on ANY web page into the referral cookie, so
 * an affiliate link works whether it points at /, /hosts, /register or a blog
 * deep link. Does nothing (zero DB queries) unless the param is present.
 */
class CaptureReferralCode
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->query('ref')) {
            ReferralAttribution::capture($request);
        }

        return $next($request);
    }
}
