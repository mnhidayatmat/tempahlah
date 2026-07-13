<?php

namespace App\Support\Billing;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\BelongsToTenantScope;

/**
 * Numeric plan-quota enforcement — the single source of truth for "can this
 * tenant add another X". Caps come from config/homestay.php → plans via
 * Plans::limit() (null = unlimited): free 1 property / 4 rooms / 20 bookings
 * per month / 1 staff, pro 3 properties / 3 staff, ultra unlimited.
 *
 * Counts are queried withoutGlobalScope + explicit tenant_id so they are
 * correct in every context: the dashboard (current tenant), the public
 * booking page (the resolved subdomain/path tenant), or a background job.
 */
class PlanLimits
{
    /** null = unlimited on the tenant's current plan. */
    public static function maxProperties(Tenant $tenant): ?int
    {
        return Plans::limit($tenant->planKey(), 'properties');
    }

    /** null = unlimited on the tenant's current plan. */
    public static function maxRoomsPerProperty(Tenant $tenant): ?int
    {
        return Plans::limit($tenant->planKey(), 'rooms_per_property');
    }

    /** null = unlimited on the tenant's current plan. */
    public static function maxBookingsPerMonth(Tenant $tenant): ?int
    {
        return Plans::limit($tenant->planKey(), 'bookings_per_month');
    }

    /** null = unlimited on the tenant's current plan. */
    public static function maxStaff(Tenant $tenant): ?int
    {
        return Plans::limit($tenant->planKey(), 'staff');
    }

    public static function canAddProperty(Tenant $tenant): bool
    {
        $max = self::maxProperties($tenant);

        return $max === null || self::propertyCount($tenant) < $max;
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
        $max = self::maxRoomsPerProperty($tenant);

        return $max === null || $requestedRooms <= $max;
    }

    public static function canAddBooking(Tenant $tenant): bool
    {
        $max = self::maxBookingsPerMonth($tenant);

        return $max === null || self::bookingsThisMonth($tenant) < $max;
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
