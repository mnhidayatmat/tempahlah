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
        $start = $this->parseStart($request->query('start'));
        $rangeDays = 14;
        $end = $start->copy()->addDays($rangeDays - 1);

        $properties = Property::query()
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'state']);

        $propertyId = (int) $request->query('property_id', 0);
        if (! $propertyId || ! $properties->firstWhere('id', $propertyId)) {
            $propertyId = $properties->first()?->id ?? 0;
        }

        $rooms = collect();
        $bookingsByRoom = collect();
        $blocksByRoom = collect();
        $stats = ['occupancy' => 0, 'revenue' => 0, 'rate' => 0];

        if ($propertyId) {
            $property = Property::with([
                'rooms' => fn ($q) => $q->orderBy('name'),
            ])->find($propertyId);

            $rooms = $property?->rooms ?? collect();
            $roomIds = $rooms->pluck('id');

            $bookings = Booking::query()
                ->with('guest:id,name')
                ->where('property_id', $propertyId)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
                ->where('check_out', '>', $start->toDateString())
                ->where('check_in', '<=', $end->toDateString())
                ->get();

            $bookingsByRoom = $bookings->groupBy('room_id');

            $blocksByRoom = CalendarBlock::query()
                ->where('property_id', $propertyId)
                ->where('ends_on', '>=', $start->toDateString())
                ->where('starts_on', '<=', $end->toDateString())
                ->get()
                ->groupBy('room_id');

            $stats = $this->computeStats($propertyId, $start, $end, $rangeDays, $rooms->count());
        }

        $days = collect(range(0, $rangeDays - 1))->map(fn ($i) => $start->copy()->addDays($i));

        return view('tenant.calendar.index', [
            'properties' => $properties,
            'propertyId' => $propertyId,
            'rooms' => $rooms,
            'bookingsByRoom' => $bookingsByRoom,
            'blocksByRoom' => $blocksByRoom,
            'days' => $days,
            'rangeDays' => $rangeDays,
            'start' => $start,
            'end' => $end,
            'stats' => $stats,
            'prevStart' => $start->copy()->subDays($rangeDays)->toDateString(),
            'nextStart' => $start->copy()->addDays($rangeDays)->toDateString(),
            'todayStart' => Carbon::today()->toDateString(),
        ]);
    }

    protected function computeStats(int $propertyId, Carbon $start, Carbon $end, int $rangeDays, int $roomCount): array
    {
        if ($roomCount === 0) {
            return ['occupancy' => 0, 'revenue' => 0, 'rate' => 0];
        }

        $bookings = Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->where('check_out', '>', $start->toDateString())
            ->where('check_in', '<=', $end->toDateString())
            ->get(['check_in', 'check_out', 'nights', 'total_amount']);

        $bookedNights = 0;
        $revenue = 0.0;
        foreach ($bookings as $b) {
            $rangeStart = $b->check_in->greaterThan($start) ? $b->check_in : $start;
            $rangeEnd = $b->check_out->lessThan($end->copy()->addDay()) ? $b->check_out : $end->copy()->addDay();
            $overlapNights = max(0, $rangeStart->diffInDays($rangeEnd));
            $bookedNights += $overlapNights;
            if ((int) $b->nights > 0) {
                $revenue += ((float) $b->total_amount) * ($overlapNights / (int) $b->nights);
            }
        }

        $available = $roomCount * $rangeDays;
        $occupancy = $available > 0 ? $bookedNights / $available : 0;
        $rate = $bookedNights > 0 ? $revenue / $bookedNights : 0;

        return [
            'occupancy' => $occupancy,
            'revenue' => $revenue,
            'rate' => $rate,
        ];
    }

    protected function parseStart(?string $raw): Carbon
    {
        if (! $raw) {
            return Carbon::today();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }
}
