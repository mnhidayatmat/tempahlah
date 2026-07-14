<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * "Booking link" — a dedicated, prominent surface for the host to grab their
 * public booking page URL and share it on social media (WhatsApp, Facebook,
 * Telegram, X, or the native share sheet on mobile → Instagram/TikTok/etc.),
 * plus ready-to-paste captions in BM + EN. The public page itself already
 * exists (Tenant::publicUrl()); this makes it findable and shareable.
 */
class BookingLinkController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->current();

        $url = $tenant->publicUrl();

        return view('tenant.booking-link.index', [
            'tenant' => $tenant,
            'publicUrl' => $url,
            'displayUrl' => preg_replace('#^https?://#', '', $url),
            'businessName' => $tenant->business_name,
            'alreadyShared' => $tenant->bookingLinkShared(),
        ]);
    }

    /**
     * Stamp the moment the host first shared/copied their link. Idempotent —
     * satisfies the onboarding "Share your booking link" checklist step and
     * dismisses the "Get set up" card. Fired via fetch() from the page when the
     * host copies, opens, or shares. Always 204 so a stamping hiccup never
     * blocks the share.
     */
    public function markShared(Request $request)
    {
        app(TenantContext::class)->current()->markBookingLinkShared();

        return response()->noContent();
    }
}
