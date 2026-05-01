<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\CalendarBlock;
use App\Models\Room;
use Carbon\CarbonInterface;

class AvailabilityService
{
    public function isAvailable(Room $room, CarbonInterface $checkIn, CarbonInterface $checkOut): bool
    {
        if ($checkIn->gte($checkOut)) {
            return false;
        }

        $bookedConflict = Booking::query()
            ->withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
            ])
            ->where('check_in', '<', $checkOut->toDateString())
            ->where('check_out', '>', $checkIn->toDateString())
            ->exists();

        if ($bookedConflict) {
            return false;
        }

        $blockConflict = CalendarBlock::query()
            ->withoutGlobalScopes()
            ->where(function ($q) use ($room) {
                $q->where('room_id', $room->id)->orWhereNull('room_id')->where('property_id', $room->property_id);
            })
            ->where('starts_on', '<', $checkOut->toDateString())
            ->where('ends_on', '>', $checkIn->toDateString())
            ->exists();

        return ! $blockConflict;
    }
}
