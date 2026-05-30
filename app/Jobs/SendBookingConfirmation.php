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

    public function __construct(public int $bookingId, public ?string $invoiceUrl = null)
    {
        // Illuminate\Bus\Queueable::$queue is declared without a default —
        // any "public $queue = ..." in subclass is a fatal trait conflict.
        // Set the queue at construct time instead.
        $this->onQueue('email');
    }

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
