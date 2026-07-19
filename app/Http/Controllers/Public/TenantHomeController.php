<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Public\PublicHomeBuilder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantHomeController extends Controller
{
    public function index(Request $request, PublicHomeBuilder $builder): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        // Remember a marketplace referral (?src=marketplace) so a booking made
        // in this session is attributed to the marketplace (3% commission).
        \App\Support\Marketplace\Attribution::capture($request, $tenant);

        $properties = Property::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with([
                // pricingRules() relation needed on each room for PricingEngine.
                'rooms:id,property_id,base_price,max_adults,max_children,beds',
                'rooms.pricingRules',
                'photos:id,property_id,path,disk,is_hero,sort_order',
                'amenities:id,key,label_bm,label_en,icon,category,sort_order',
            ])
            ->orderBy('name')
            ->get();

        $data = $builder->build($tenant, $properties, $request);
        $data['prefill'] = $this->prefill($request, $properties, $tenant);

        return view('public-tenant.home', $data);
    }

    /**
     * Seed the booking form from a link the host sent this guest ("Send booking
     * form" in the dashboard), e.g. ?property_id=3&check_in=…&guests=4&pay=manual
     *
     * The guest may still change any of it — the price is recomputed server-side
     * on submit and availability is re-checked there — so these params are a
     * convenience, not a contract. Anything unusable is dropped silently rather
     * than 404'ing: a stale link should still open a working booking page.
     */
    protected function prefill(Request $request, $properties, Tenant $tenant): ?array
    {
        if (! $request->hasAny(['property_id', 'check_in', 'check_out', 'guests', 'pay', 'price'])) {
            return null;
        }

        $property = $properties->firstWhere('id', (int) $request->query('property_id'));

        $checkIn = $this->parseDate($request->query('check_in'));
        $checkOut = $this->parseDate($request->query('check_out'));

        // Never seed a range the calendar would reject. MYT "today" — the app is
        // UTC, so today() is yesterday between 00:00–08:00 MYT.
        if ($checkIn && $checkIn->lt(now(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->startOfDay())) {
            $checkIn = null;
        }
        if (! $checkIn || ($checkOut && $checkOut->lte($checkIn))) {
            $checkOut = null;
        }

        $guests = (int) $request->query('guests', 0);
        $pay = (string) $request->query('pay', '');

        // Host-set custom price. Only surface it when the HMAC still verifies
        // against the exact tenant + property + dates + amount — so a guest who
        // edits the price (or dates) in the URL never sees a "trusted" figure.
        // Both `price` and `psig` are threaded through to the booking form and
        // RE-verified server-side on submit; this is display-only.
        $price = null;
        $psig = null;
        if ($property && $checkIn && $checkOut) {
            $rawPrice = $request->query('price');
            $sig = (string) $request->query('psig', '');
            if (\App\Support\Booking\QuotedPrice::verify(
                $tenant->id, $property->id, $checkIn->toDateString(), $checkOut->toDateString(), $rawPrice, $sig
            )) {
                $price = \App\Support\Booking\QuotedPrice::normalizeAmount($rawPrice);
                $psig = $sig;
            }
        }

        $prefill = array_filter([
            'property_id' => $property?->id,
            'check_in' => $checkIn?->toDateString(),
            'check_out' => $checkOut?->toDateString(),
            // Accept any sane positive count (matching the property max_guests
            // ceiling); the public page then clamps it to the property's actual
            // sleeping capacity. A hardcoded 20 here silently dropped larger
            // whole-house groups (e.g. a host prefilling 30) back to the
            // property's default_guests.
            'guests' => ($guests >= 1 && $guests <= 200) ? $guests : null,
            'pay' => in_array($pay, ['manual', 'gateway'], true) ? $pay : null,
            // Verified agreed price (accommodation subtotal) + its signature.
            'price' => $price,
            'psig' => $psig,
        ], fn ($v) => $v !== null);

        return $prefill ?: null;
    }

    /** Strict Y-m-d, so "2026-13-45" is rejected rather than rolled over. */
    protected function parseDate(?string $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }
}
