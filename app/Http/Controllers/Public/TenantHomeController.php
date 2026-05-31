<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantHomeController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('subdomain_tenant');

        $properties = Property::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with([
                'rooms:id,property_id,base_price,max_adults,max_children,beds',
                // Cover photo for the hero banner. Minimal columns so we can
                // pick the is_hero one (else fall back to first by sort_order).
                'photos:id,property_id,path,disk,is_hero,sort_order',
            ])
            ->orderBy('name')
            ->get();

        $covers = ['beach', 'highland', 'kampung', 'heritage', 'city'];
        foreach ($properties as $property) {
            $property->cover_kind  = $covers[crc32((string) $property->id) % count($covers)];
            $property->starting_rate = (float) ($property->rooms->min('base_price') ?? 0);
            $property->sleeps_total  = (int) $property->rooms->sum(
                fn ($r) => (int) $r->max_adults + (int) $r->max_children
            );
            $property->beds_total = (int) $property->rooms->sum(fn ($r) => (int) $r->beds);

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

        return view('public-tenant.home', [
            'tenant'           => $tenant,
            'properties'       => $properties,
            'contactPhone'     => $contactPhone,
            'bookedByProperty' => $bookedByProperty,
        ]);
    }
}
