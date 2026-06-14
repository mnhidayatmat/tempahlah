<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CalendarBlock;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $cursor = $this->parseCursor($request->query('cursor'));
        $monthStart = $cursor->copy()->startOfMonth();
        $monthEnd = $cursor->copy()->endOfMonth();

        $properties = Property::query()
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'state', 'pricing_mode']);

        $propertyId = (int) $request->query('property_id', 0);
        if (! $propertyId || ! $properties->firstWhere('id', $propertyId)) {
            $propertyId = $properties->first()?->id ?? 0;
        }

        $selectedDay = $this->parseDay($request->query('day'), $monthStart, $monthEnd);

        $rooms = collect();
        $bookings = collect();
        $blocks = collect();

        if ($propertyId) {
            $property = Property::with([
                'rooms' => fn ($q) => $q->orderBy('name'),
            ])->find($propertyId);

            $rooms = $property?->rooms ?? collect();

            $bookings = Booking::query()
                ->with(['guest:id,name', 'room:id,name,base_price'])
                ->where('property_id', $propertyId)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
                ->where('check_out', '>', $monthStart->toDateString())
                ->where('check_in', '<=', $monthEnd->toDateString())
                ->get();

            $blocks = CalendarBlock::query()
                ->where('property_id', $propertyId)
                ->where('ends_on', '>=', $monthStart->toDateString())
                ->where('starts_on', '<=', $monthEnd->toDateString())
                ->get();
        }

        // Build bookings-by-date map (each day a booking touches)
        $bookingsByDate = [];
        $eventsByDate = [];
        foreach ($bookings as $b) {
            $d = $b->check_in->copy();
            while ($d->lt($b->check_out)) {
                $iso = $d->toDateString();
                $bookingsByDate[$iso][] = $b;
                $d->addDay();
            }
            $iso = $b->check_in->toDateString();
            $eventsByDate[$iso]['checkins'][] = $b;
            $iso = $b->check_out->toDateString();
            $eventsByDate[$iso]['checkouts'][] = $b;
        }

        // Build days grid (pad to weeks, Sunday-first)
        $days = [];
        $startOffset = $monthStart->dayOfWeek; // 0 = Sunday
        for ($i = 0; $i < $startOffset; $i++) {
            $days[] = null;
        }
        $d = $monthStart->copy();
        while ($d->lte($monthEnd)) {
            $days[] = $d->copy();
            $d->addDay();
        }
        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        // Month stats
        $bookedNights = 0;
        $revenue = 0.0;
        foreach ($bookings as $b) {
            $rangeStart = $b->check_in->greaterThan($monthStart) ? $b->check_in : $monthStart;
            $rangeEnd = $b->check_out->lessThan($monthEnd->copy()->addDay()) ? $b->check_out : $monthEnd->copy()->addDay();
            $overlap = max(0, $rangeStart->diffInDays($rangeEnd));
            $bookedNights += $overlap;
            if ((int) $b->nights > 0) {
                $revenue += ((float) $b->total_amount) * ($overlap / (int) $b->nights);
            }
        }
        $available = max(1, $rooms->count() * $monthEnd->day);
        $occupancyPct = (int) round(($bookedNights / $available) * 100);
        $monthBookingsCount = $bookings->filter(fn ($b) =>
            $b->check_out->gt($monthStart) && $b->check_in->lte($monthEnd)
        )->count();

        $property = $properties->firstWhere('id', $propertyId);

        return view('tenant.calendar.index', [
            'properties' => $properties,
            'propertyId' => $propertyId,
            'property' => $property,
            'rooms' => $rooms,
            'bookingsByDate' => $bookingsByDate,
            'eventsByDate' => $eventsByDate,
            'days' => $days,
            'cursor' => $cursor,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'monthLabel' => $cursor->format('F Y'),
            'prevCursor' => $cursor->copy()->subMonth()->format('Y-m'),
            'nextCursor' => $cursor->copy()->addMonth()->format('Y-m'),
            'todayCursor' => Carbon::today()->format('Y-m'),
            'todayIso' => Carbon::today()->toDateString(),
            'selectedDay' => $selectedDay,
            'occupancyPct' => $occupancyPct,
            'monthRevenue' => $revenue,
            'monthBookings' => $monthBookingsCount,
            'monthNights' => $bookedNights,
        ]);
    }

    protected function parseCursor(?string $raw): Carbon
    {
        if (! $raw) {
            return Carbon::today()->startOfMonth();
        }
        try {
            return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
        } catch (\Throwable) {
            return Carbon::today()->startOfMonth();
        }
    }

    protected function parseDay(?string $raw, Carbon $monthStart, Carbon $monthEnd): ?string
    {
        if (! $raw) return null;
        try {
            $d = Carbon::parse($raw);
            if ($d->lt($monthStart) || $d->gt($monthEnd)) return null;
            return $d->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
