<?php

namespace App\Jobs;

use App\Mail\CheckInInstructionsMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Fires email + WhatsApp check-in instructions for a single booking.
 * Dispatched by the scheduled DispatchCheckinInstructions command.
 */
class SendCheckInInstructions implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with('property', 'tenant', 'guest')
            ->find($this->bookingId);

        if (! $booking) return;
        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW], true)) return;

        if ($booking->guest?->email) {
            Mail::to($booking->guest->email)->queue(new CheckInInstructionsMail($booking));
        }

        WhatsappMessenger::dispatchCheckin($booking);

        $booking->update(['checkin_instructions_sent_at' => now()]);
    }
}
