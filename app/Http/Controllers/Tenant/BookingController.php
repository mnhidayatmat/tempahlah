<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Support\Tenancy\TenantContext;

class BookingController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $bookings = collect();
        if (class_exists(Booking::class)) {
            $bookings = Booking::query()
                ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
                ->orderByDesc('check_in')
                ->paginate(20);
        }
        return view('tenant.bookings.index', compact('bookings'));
    }

    public function show($id)
    {
        $tenant = app(TenantContext::class)->current();
        $booking = Booking::query()
            ->when(method_exists(Booking::class, 'scopeForTenant'), fn ($q) => $q->forTenant($tenant))
            ->findOrFail($id);
        return view('tenant.bookings.show', compact('booking'));
    }
}
