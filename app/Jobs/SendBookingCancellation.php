<?php

namespace App\Jobs;

use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Fan-out: email + WhatsApp the guest that their booking was cancelled.
 * Each arm is independent — a failure in one must not cancel the other.
 */
class SendBookingCancellation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bookingId,
        public ?string $reason = null,
    ) {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest'])
            ->find($this->bookingId);

        if (! $booking) return;

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();
        $email = $lead?->email ?? $booking->guest?->email;

        if ($email) {
            try {
                Mail::to($email)->send(new BookingCancelledMail($booking, $this->reason));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            WhatsappMessenger::dispatchCancellation($booking, $this->reason);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
