<?php

namespace App\Jobs;

use App\Mail\BookingInvoiceMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\WhatsApp\WhatsappMessenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Fan-out: email the invoice PDF + dispatch a WhatsApp pay-link message.
 *
 * Sent right after a public booking is created on the tenant subdomain.
 * Either arm independently no-ops if the prerequisite isn't met (no email
 * → skip mail, no connected WhatsApp / opted-out guest → skip WA), but a
 * failure in one arm must not cancel the other — wrap each in its own
 * try/catch.
 */
class SendBookingInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bookingId,
        public int $invoiceId,
        public string $payUrl,
        public bool $manual = false,
    ) {
        // Illuminate\Bus\Queueable::$queue has no default — setting via
        // onQueue() avoids the trait property collision.
        $this->onQueue('email');
    }

    public function handle(): void
    {
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property', 'tenant', 'guest'])
            ->find($this->bookingId);
        $invoice = Invoice::withoutGlobalScopes()->find($this->invoiceId);

        if (! $booking || ! $invoice) return;

        $lead = $booking->bookingGuests()->where('is_lead', true)->first();

        // Email arm — only if we have a recipient address.
        if ($lead?->email) {
            try {
                Mail::to($lead->email)->send(new BookingInvoiceMail($booking, $invoice, $this->payUrl, $this->manual));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // WhatsApp arm — messenger handles all gating internally.
        // Pass the Invoice so the messenger can attach the PDF as a
        // WhatsApp document via a 7-day signed Spaces URL.
        try {
            WhatsappMessenger::dispatchInvoice($booking, $this->payUrl, $invoice, $this->manual);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
