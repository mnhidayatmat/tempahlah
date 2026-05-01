<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $start = Carbon::today();
        $days = collect(range(0, 13))->map(fn ($i) => $start->copy()->addDays($i));

        $properties = collect();
        if (class_exists(Property::class)) {
            $properties = Property::query()
                ->when(method_exists(Property::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->orderBy('name')
                ->get();
        }

        $bookings = collect();
        if (class_exists(Booking::class)) {
            $bookings = Booking::query()
                ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->where('check_out', '>=', $start)
                ->where('check_in', '<=', $start->copy()->addDays(14))
                ->get()
                ->groupBy('property_id');
        }

        return view('tenant.calendar.index', compact('days', 'properties', 'bookings', 'start'));
    }
}
