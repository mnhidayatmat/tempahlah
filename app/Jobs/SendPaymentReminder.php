<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMail;
use App\Models\Booking;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminder implements ShouldQueue
{
    use Queueable;

    public $queue = 'email';

    public function __construct(public int $bookingId, public ?string $paymentUrl = null) {}

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with('property', 'tenant', 'guest')
            ->find($this->bookingId);

        if (! $booking) return;
        if ($booking->status === Booking::STATUS_CANCELLED) return;
        if ($booking->balance_paid_at !== null) return;

        if ($booking->guest?->email) {
            Mail::to($booking->guest->email)->queue(new PaymentReminderMail($booking));
        }

        WhatsappMessenger::dispatchReminder($booking, $this->paymentUrl);
    }
}
