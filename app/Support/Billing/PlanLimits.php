<?php

namespace App\Support\Billing;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;

/**
 * Free-tier quota enforcement. The advertised Free plan caps
 * (config/homestay.php → free_tier_limits) were defined but enforced
 * nowhere — a free tenant could create unlimited properties, rooms and
 * bookings. This is the single source of truth for those checks.
 *
 * Paid tenants (including trialing / comped / in-grace — anything
 * Tenant::isPaid() returns true for) are never limited.
 *
 * Counts are queried withoutGlobalScope + explicit tenant_id so they are
 * correct in every context: the dashboard (current tenant), the public
 * booking page (the resolved subdomain tenant), or a background job.
 */
class PlanLimits
{
    public static function maxProperties(): int
    {
        return (int) config('homestay.free_tier_limits.properties', 1);
    }

    public static function maxRoomsPerProperty(): int
    {
        return (int) config('homestay.free_tier_limits.rooms_per_property', 3);
    }

    public static function maxBookingsPerMonth(): int
    {
        return (int) config('homestay.free_tier_limits.bookings_per_month', 20);
    }

    public static function canAddProperty(Tenant $tenant): bool
    {
        if ($tenant->isPaid()) {
            return true;
        }

        return self::propertyCount($tenant) < self::maxProperties();
    }

    public static function propertyCount(Tenant $tenant): int
    {
        return Property::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->count();
    }

    /**
     * Would creating a property with $requestedRooms rooms stay within the
     * per-property room cap? Whole-house properties always have one room, so
     * this only ever bites a per-room property with too many bedrooms.
     */
    public static function roomsAllowed(Tenant $tenant, int $requestedRooms): bool
    {
        if ($tenant->isPaid()) {
            return true;
        }

        return $requestedRooms <= self::maxRoomsPerProperty();
    }

    public static function canAddBooking(Tenant $tenant): bool
    {
        if ($tenant->isPaid()) {
            return true;
        }

        return self::bookingsThisMonth($tenant) < self::maxBookingsPerMonth();
    }

    /**
     * Bookings created for this tenant in the current calendar month.
     * Cancelled / no-show bookings do not consume the quota — a host who
     * cancels a mistaken booking gets the slot back.
     */
    public static function bookingsThisMonth(Tenant $tenant): int
    {
        return Booking::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }
}
