<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\Expense;
use App\Models\LaundryTask;
use App\Models\MaintenanceTicket;
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

    /**
     * Gross sales — the full amount billed to guests (accommodation + SST +
     * tourism tax + booking fee). This is the app's headline "revenue" figure
     * used across the dashboard, so the method name is kept for compatibility.
     */
    public function revenue(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): float
    {
        return (float) Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereBetween('check_in', [$start, $end])
            ->sum('total_amount');
    }

    /**
     * Net revenue — the host's own income: gross sales minus the government
     * pass-through taxes (SST + tourism tax), which the host collects but
     * remits and never keeps. For a host who charges neither, this equals
     * gross sales. This is the top line the profit figure is built from.
     */
    public function netRevenue(CarbonInterface $start, CarbonInterface $end, ?int $propertyId = null): float
    {
        return (float) Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->whereBetween('check_in', [$start, $end])
            ->selectRaw('COALESCE(SUM(total_amount - COALESCE(sst_amount, 0) - COALESCE(tourism_tax_amount, 0)), 0) as v')
            ->value('v');
    }

    /**
     * Operating expenses recorded in the period: costed cleaning + laundry +
     * maintenance tasks plus the standalone expenses ledger. Mirrors the
     * dashboard's "this month cost" tile so the two never disagree. All four
     * models are tenant-scoped, so this is already the current tenant's spend.
     */
    public function expenses(CarbonInterface $start, CarbonInterface $end): float
    {
        $cleaning = (float) CleaningTask::query()->whereBetween('scheduled_at', [$start, $end])->sum('cost');
        $laundry = (float) LaundryTask::query()->whereBetween('pickup_at', [$start, $end])->sum('cost');
        $maintenance = (float) MaintenanceTicket::query()->whereBetween('resolved_at', [$start, $end])->sum('cost');
        $other = (float) Expense::query()->whereBetween('incurred_at', [$start, $end])->sum('amount');

        return round($cleaning + $laundry + $maintenance + $other, 2);
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
