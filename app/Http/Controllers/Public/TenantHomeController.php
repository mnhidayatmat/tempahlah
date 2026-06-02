<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Pricing\PricingEngine;
use App\Support\Tenancy\BelongsToTenantScope;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantHomeController extends Controller
{
    /** How many days ahead to pre-compute dynamic per-date prices for the
     *  calendar. A full 365 covers a year of forward navigation. Cheap
     *  now that PricingEngine uses the eager-loaded `pricingRules`
     *  collection in PHP (1 DB query per page load total, not per date).
     *  Was 60 — caused weekend / festive rules to silently stop applying
     *  once the user paged past the cutoff. */
    private const RATE_WINDOW_DAYS = 365;

    public function index(Request $request, PricingEngine $pricing): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        $properties = Property::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with([
                // pricingRules() relation needed on each room for PricingEngine.
                'rooms:id,property_id,base_price,max_adults,max_children,beds',
                'rooms.pricingRules',
                // Cover photo for the hero banner + photo strip on Utama.
                'photos:id,property_id,path,disk,is_hero,sort_order',
                // Amenities for the Utama "Top amenities" chip row.
                // Eager-loaded with their icon + locale labels.
                'amenities:id,key,label_bm,label_en,icon,category,sort_order',
            ])
            ->orderBy('name')
            ->get();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        // Per-date dynamic rates window: today → today+60d. For each visible
        // date in the calendar, PricingEngine evaluates every active pricing
        // rule on the property's room(s) and returns the adjusted rate.
        // For per-room properties we take the CHEAPEST room's adjusted rate
        // for that date (matches the "from RM X" semantics on the card).
        $window = CarbonPeriod::create(
            now()->startOfDay(),
            now()->addDays(self::RATE_WINDOW_DAYS - 1)->startOfDay(),
        );
        foreach ($properties as $property) {
            $property->cover_kind  = $covers[crc32((string) $property->id) % count($covers)];
            $property->sleeps_total  = (int) $property->rooms->sum(
                fn ($r) => (int) $r->max_adults + (int) $r->max_children
            );
            $property->beds_total = (int) $property->rooms->sum(fn ($r) => (int) $r->beds);
            // Pre-filled value for the public "tetamu" stepper. Tenant
            // sets this in /dashboard/properties/{id}/edit; falls back
            // to floor(sleeps/2) when blank (see Property accessor).
            $property->default_guests_resolved = $property->effectiveDefaultGuests();

            $rates = [];
            foreach ($window as $date) {
                // Cheapest room's adjusted rate for this night.
                $cheapest = null;
                foreach ($property->rooms as $room) {
                    $price = $pricing->quoteNight($room, $date);
                    if ($cheapest === null || $price < $cheapest) {
                        $cheapest = $price;
                    }
                }
                if ($cheapest !== null) {
                    $rates[$date->toDateString()] = (float) $cheapest;
                }
            }
            $property->rates_by_date = $rates;

            // "Starting from" = cheapest rate seen across the whole 60-day
            // window. So pricing rules that DROP the price (off-peak
            // discounts) get reflected in the headline "from RM X" too.
            $property->starting_rate = $rates === []
                ? (float) ($property->rooms->min('base_price') ?? 0)
                : (float) min($rates);

            // Resolve cover photo URL: prefer the explicit hero, else first
            // by sort_order, else null (view falls back to the gradient).
            $cover = $property->photos->firstWhere('is_hero', true)
                ?? $property->photos->first();
            $property->cover_photo_url = $cover?->url();
        }

        // Per-property booked-date sets — flatten each future booking into the
        // YYYY-MM-DD strings the calendar should disable. Skip cancelled/no-show.
        $bookedByProperty = [];
        if ($properties->isNotEmpty()) {
            $bookings = Booking::query()
                ->withoutGlobalScope(BelongsToTenantScope::class)
                ->whereIn('property_id', $properties->pluck('id'))
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->where('check_out', '>=', now()->startOfDay())
                ->get(['property_id', 'check_in', 'check_out']);

            foreach ($properties as $property) {
                $bookedByProperty[$property->id] = [];
            }

            foreach ($bookings as $b) {
                $cursor = $b->check_in->copy();
                while ($cursor->lt($b->check_out)) {
                    $bookedByProperty[$b->property_id][] = $cursor->toDateString();
                    $cursor->addDay();
                }
            }

            foreach ($bookedByProperty as $pid => $dates) {
                $bookedByProperty[$pid] = array_values(array_unique($dates));
            }
        }

        $contactPhone = preg_replace('/\D/', '', $tenant->business_phone ?? '');

        // Discreet "Open dashboard" footer link for the tenant owner / staff
        // when they visit their own public page. Session is shared across
        // subdomains via SESSION_DOMAIN=.tempahlah.com, so Auth::user() is
        // populated here. Gated on an ACTIVE tenant_users pivot for THIS
        // tenant — never show a "Dashboard" link to a host of a different
        // tenant (would drop them into the wrong tenant context).
        $ownerCanAccess = false;
        if ($user = $request->user()) {
            $ownerCanAccess = $user->tenants()
                ->wherePivot('status', 'active')
                ->whereKey($tenant->id)
                ->exists();
        }

        // When the tenant has an enabled Toyyibpay integration, the public
        // page shows the "Reserve & pay deposit" form CTA. Otherwise it
        // falls back to the original wa.me deeplink so the page still
        // works out-of-the-box during free-tier onboarding.
        $toyyibpayConfigured = TenantIntegration::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', TenantIntegration::PROVIDER_TOYYIBPAY)
            ->where('enabled', true)
            ->exists();

        return view('public-tenant.home', [
            'tenant'              => $tenant,
            'properties'          => $properties,
            'contactPhone'        => $contactPhone,
            'bookedByProperty'    => $bookedByProperty,
            'toyyibpayConfigured' => $toyyibpayConfigured,
            'ownerCanAccess'      => $ownerCanAccess,
            'apexUrl'             => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
