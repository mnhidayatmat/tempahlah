<?php

namespace App\Services\Public;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Pricing\PricingEngine;
use App\Support\Tenancy\BelongsToTenantScope;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Builds the view payload for the tenant public booking page
 * (public-tenant.home). Shared by the tenant subdomain landing
 * (TenantHomeController) and the marketplace listing detail
 * (MarketplaceController@show), so both render the exact same page — the
 * marketplace detail just passes a single property + marketplace context.
 *
 * Each property in $properties must already be eager-loaded with
 * rooms + rooms.pricingRules + photos + amenities.
 */
class PublicHomeBuilder
{
    /** Days ahead to pre-compute dynamic per-date prices for the calendar. */
    private const RATE_WINDOW_DAYS = 365;

    public function __construct(protected PricingEngine $pricing) {}

    public function build(Tenant $tenant, Collection $properties, Request $request): array
    {
        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        // MYT calendar days — the rate window must start on the Malaysian "today",
        // not the UTC one (off by a day between 00:00–08:00 MYT).
        $tz = config('homestay.timezone', 'Asia/Kuala_Lumpur');
        $window = CarbonPeriod::create(
            now($tz)->startOfDay(),
            now($tz)->addDays(self::RATE_WINDOW_DAYS - 1)->startOfDay(),
        );

        foreach ($properties as $property) {
            $property->cover_kind = $covers[crc32((string) $property->id) % count($covers)];
            $property->sleeps_total = (int) $property->rooms->sum(
                fn ($r) => (int) $r->max_adults + (int) $r->max_children
            );
            $property->beds_total = (int) $property->rooms->sum(fn ($r) => (int) $r->beds);
            $property->default_guests_resolved = $property->effectiveDefaultGuests();

            $rates = [];
            foreach ($window as $date) {
                $cheapest = null;
                foreach ($property->rooms as $room) {
                    $price = $this->pricing->quoteNight($room, $date);
                    if ($cheapest === null || $price < $cheapest) {
                        $cheapest = $price;
                    }
                }
                if ($cheapest !== null) {
                    $rates[$date->toDateString()] = (float) $cheapest;
                }
            }
            $property->rates_by_date = $rates;
            $property->starting_rate = $rates === []
                ? (float) ($property->rooms->min('base_price') ?? 0)
                : (float) min($rates);

            $cover = $property->photos->firstWhere('is_hero', true) ?? $property->photos->first();
            $property->cover_photo_url = $cover?->url();
        }

        // Per-property booked-date sets (only HELD bookings grey out the calendar).
        $bookedByProperty = [];
        if ($properties->isNotEmpty()) {
            foreach ($properties as $property) {
                $bookedByProperty[$property->id] = [];
            }

            $bookings = Booking::query()
                ->withoutGlobalScope(BelongsToTenantScope::class)
                ->whereIn('property_id', $properties->pluck('id'))
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->where('check_out', '>=', now($tz)->startOfDay()->toDateString())
                ->where(function ($q) {
                    $q->whereIn('status', [
                        Booking::STATUS_CONFIRMED,
                        Booking::STATUS_CHECKED_IN,
                        Booking::STATUS_CHECKED_OUT,
                    ])->orWhereNotNull('deposit_paid_at')->orWhereNotNull('balance_paid_at');
                })
                ->get(['property_id', 'check_in', 'check_out']);

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

        // Per-property published guest testimonials + their rating summary.
        // Reviews carry a tenant global scope; bypass it (this is a public,
        // no-tenant-context request).
        $reviewsByProperty = [];
        $ratingByProperty = [];
        if ($properties->isNotEmpty()) {
            foreach ($properties as $property) {
                $reviewsByProperty[$property->id] = [];
                $ratingByProperty[$property->id] = ['avg' => null, 'count' => 0];
            }

            $reviews = Review::query()
                ->withoutGlobalScope(BelongsToTenantScope::class)
                ->where('reviewer_type', Review::REVIEWER_GUEST)
                ->where('subject_type', Property::class)
                ->whereIn('subject_id', $properties->pluck('id'))
                ->where('is_published', true)
                ->with(['booking:id,check_out,guest_id', 'booking.guest:id,name'])
                ->get()
                // Order for the horizontal scroll (leftmost = shown first):
                //   1) highest rating, 2) longer / more detailed comment,
                //   3) newest. So among equal 5-star reviews the wordier ones
                // lead on the left. mb_strlen keeps multibyte (Malay) accurate.
                ->sort(function ($a, $b) {
                    return [$b->rating_overall, mb_strlen((string) $b->comment), $b->created_at?->getTimestamp() ?? 0]
                        <=> [$a->rating_overall, mb_strlen((string) $a->comment), $a->created_at?->getTimestamp() ?? 0];
                })
                ->values();

            foreach ($reviews as $r) {
                $reviewsByProperty[$r->subject_id][] = [
                    'name'    => $r->displayName(),
                    'rating'  => (int) $r->rating_overall,
                    'comment' => (string) $r->comment,
                    'stay'    => $r->stayLabel(),
                ];
            }

            foreach ($reviewsByProperty as $pid => $list) {
                $n = count($list);
                $ratingByProperty[$pid] = [
                    'avg'   => $n ? round(array_sum(array_column($list, 'rating')) / $n, 1) : null,
                    'count' => $n,
                ];
                // Cap the payload — a page doesn't need hundreds inline.
                $reviewsByProperty[$pid] = array_slice($list, 0, 24);
            }
        }

        $ownerCanAccess = false;
        if ($user = $request->user()) {
            $ownerCanAccess = $user->tenants()
                ->wherePivot('status', 'active')
                ->whereKey($tenant->id)
                ->exists();
        }

        // Which online gateway (if any) is active for this tenant. Gateway-
        // agnostic: true when ANY of Toyyibpay / Billplz / SecurePay is enabled,
        // resolved by the same dispatcher the booking flow bills through — so
        // switching gateways in the dashboard flips the public page's online-pay
        // option correctly. (`$toyyibpayConfigured` keeps its name for the
        // view/Alpine, but now means "an online gateway is configured".)
        $gatewayBill = app(\App\Actions\Payments\CreateGatewayBill::class);
        $activeGateway = $gatewayBill->resolveProvider($tenant->id); // 'toyyibpay' | 'billplz' | 'securepay' | null
        $toyyibpayConfigured = $activeGateway !== null;
        $gatewayName = \App\Actions\Payments\CreateGatewayBill::displayName($activeGateway);

        return [
            'tenant'               => $tenant,
            'properties'           => $properties,
            'contactPhone'         => preg_replace('/\D/', '', $tenant->business_phone ?? ''),
            'bookedByProperty'     => $bookedByProperty,
            'reviewsByProperty'    => $reviewsByProperty,
            'ratingByProperty'     => $ratingByProperty,
            'toyyibpayConfigured'  => $toyyibpayConfigured,
            'gatewayName'          => $gatewayName,
            'manualInstructions'   => $tenant->manualPaymentInstructions(),
            'ownerCanAccess'       => $ownerCanAccess,
            'apexUrl'              => rtrim((string) config('app.url'), '/'),
            // Marketplace detail overrides these (see MarketplaceController@show).
            'marketplaceContext'   => false,
            'backUrl'              => null,
        ];
    }
}
