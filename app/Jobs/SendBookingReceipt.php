<?php

namespace App\Jobs;

use App\Mail\BookingReceiptMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Fan-out: email the receipt PDF + dispatch a WhatsApp payment-confirmation
 * message. Triggered server-to-server from the Toyyibpay webhook, so it
 * runs even if the customer abandoned the return-page redirect.
 */
class SendBookingReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bookingId,
        public int $receiptId,
        public int $paymentId,
    ) {
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest'])
            ->find($this->bookingId);
        $receipt = Invoice::withoutGlobalScopes()->find($this->receiptId);
        $payment = Payment::withoutGlobalScopes()->find($this->paymentId);

        if (! $booking || ! $receipt || ! $payment) return;

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        if ($lead?->email) {
            try {
                Mail::to($lead->email)->send(new BookingReceiptMail($booking, $receipt, $payment));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            WhatsappMessenger::dispatchReceipt($booking, $receipt, $payment);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
