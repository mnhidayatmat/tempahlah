<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;

class StatisticsService
{
    public function occupancy(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): float
    {
        $totalRooms = Room::when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->where('status', 'active')->count();

        if ($totalRooms === 0) {
            return 0.0;
        }

        $totalNights = $start->diffInDays($end) * $totalRooms;
        if ($totalNights === 0) {
            return 0.0;
        }

        $bookedNights = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->where('check_in', '<', $end->toDateString())
            ->where('check_out', '>', $start->toDateString())
            ->sum('nights');

        return round(($bookedNights / $totalNights) * 100, 2);
    }

    public function adr(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): float
    {
        $rows = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereBetween('check_in', [$start, $end])
            ->selectRaw('SUM(base_amount) as revenue, SUM(nights) as nights')
            ->first();

        if (! $rows || $rows->nights == 0) {
            return 0.0;
        }

        return round((float) $rows->revenue / (int) $rows->nights, 2);
    }

    public function revenue(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): float
    {
        return (float) Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereBetween('check_in', [$start, $end])
            ->sum('total_amount');
    }

    /**
     * Number of revenue-bearing bookings in the range — same booking set the
     * revenue figure is summed over (confirmed / checked-in / checked-out,
     * keyed by check-in date) so the two series always reconcile.
     */
    public function bookingCount(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): int
    {
        return Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereBetween('check_in', [$start, $end])
            ->count();
    }
}
