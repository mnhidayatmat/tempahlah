<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentReminder implements ShouldQueue
{
    use Queueable;

    public string $queue = 'email';

    public function __construct(public int $bookingId) {}

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()->find($this->bookingId);

        if (! $booking || $booking->status === Booking::STATUS_CANCELLED) {
            return;
        }

        if ($booking->balance_paid_at !== null) {
            return;
        }

        Log::info('Payment reminder dispatched', ['booking' => $booking->reference, 'balance_due' => $booking->balanceDue()]);
    }
}
