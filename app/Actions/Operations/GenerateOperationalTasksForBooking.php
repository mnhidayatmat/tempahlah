<?php

namespace App\Actions\Operations;

use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use Laravel\Pennant\Feature;

class GenerateOperationalTasksForBooking
{
    public function execute(Booking $booking): void
    {
        if (! Feature::value('auto_operational_tasks')) {
            return;
        }

        $checkOut = $booking->check_out->copy()->setTime(
            ...explode(':', $booking->property->check_out_time)
        );

        CleaningTask::firstOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'property_id' => $booking->property_id,
                'room_id' => $booking->room_id,
                'booking_id' => $booking->id,
                'type' => CleaningTask::TYPE_LIGHT,
            ],
            [
                'status' => CleaningTask::STATUS_PENDING,
                'scheduled_at' => $checkOut->copy()->addHour(),
            ],
        );

        LaundryTask::firstOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'property_id' => $booking->property_id,
                'booking_id' => $booking->id,
            ],
            [
                'status' => LaundryTask::STATUS_PENDING,
                'pickup_at' => $checkOut->copy()->addHours(2),
                'expected_return_at' => $checkOut->copy()->addDay(),
                'item_count' => max(2, $booking->adults * 2),
            ],
        );
    }
}
