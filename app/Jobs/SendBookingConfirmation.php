<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\CommunicationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmation implements ShouldQueue
{
    use Queueable;

    public string $queue = 'email';

    public function __construct(public int $bookingId) {}

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()->with('bookingGuests', 'property')->findOrFail($this->bookingId);
        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        if (! $lead?->email) {
            return;
        }

        Mail::to($lead->email)->send(new BookingConfirmationMail($booking));
    }
}
