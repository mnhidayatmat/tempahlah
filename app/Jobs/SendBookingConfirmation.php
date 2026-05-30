<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmation implements ShouldQueue
{
    use Queueable;

    // Untyped — Laravel 12's Queueable trait declares $queue without a type
    // and refuses incompatible composition (PHP fatal at class load).
    public $queue = 'email';

    public function __construct(public int $bookingId, public ?string $invoiceUrl = null) {}

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with('bookingGuests', 'property', 'tenant', 'guest')
            ->findOrFail($this->bookingId);

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        // Email arm — only if we have an address.
        if ($lead?->email) {
            Mail::to($lead->email)->send(new BookingConfirmationMail($booking));
        }

        // WhatsApp arm — Messenger handles all gating (tenant pref, connected,
        // guest opt-out, recipient guard).
        WhatsappMessenger::dispatchConfirmation($booking, $this->invoiceUrl);
    }
}
