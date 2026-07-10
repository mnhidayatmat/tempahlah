<?php

namespace App\Console\Commands;

use App\Actions\Booking\CancelBooking;
use App\Actions\Payments\CreateGatewayBill;
use App\Jobs\SendBookingInvoice;
use App\Jobs\SendPaymentReminder;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayException;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Drives the booking payment lifecycle once an hour:
 *
 *   A. Fee chase   — pending online bookings whose deposit/booking fee is
 *                    still unpaid get one more invoice nudge (email + WA)
 *                    partway through the pay window.
 *   B. Fee cancel  — those still unpaid past fee_due_at auto-cancel.
 *   C. Balance reminder — confirmed bookings with an outstanding balance get
 *                    a reminder (email + WA) once they reach the tenant's
 *                    "X days before check-in" mark, with a fresh pay link.
 *   D. Balance cancel — confirmed bookings still unpaid past the tenant's
 *                    balance deadline (due date, or check-in day) auto-cancel.
 *
 * All windows/lead-times are tenant-configurable (see Tenant policy helpers).
 * Idempotency comes from the fee_reminder_sent_at / full_payment_reminder_sent_at
 * guards and from terminal status changes.
 */
class ProcessPaymentLifecycle extends Command
{
    protected $signature = 'bookings:process-payment-lifecycle';

    protected $description = 'Chase + auto-cancel unpaid booking fees and balances per each tenant\'s payment policy';

    public function handle(CreateGatewayBill $createBill, CancelBooking $cancelBooking): int
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        $feeChased = $this->chaseUnpaidFees($now);
        $feeCancelled = $this->cancelUnpaidFees($now, $cancelBooking);
        $balanceReminded = $this->remindUnpaidBalances($today, $createBill);
        $balanceCancelled = $this->cancelUnpaidBalances($today, $cancelBooking);

        $this->info("Fee: {$feeChased} chased, {$feeCancelled} cancelled. "
            . "Balance: {$balanceReminded} reminded, {$balanceCancelled} cancelled.");

        return self::SUCCESS;
    }

    /**
     * A. Re-send the invoice/pay-link for pending online bookings whose
     * booking fee is unpaid, once they're past the midpoint of the pay window.
     */
    protected function chaseUnpaidFees(Carbon $now): int
    {
        $count = 0;

        $this->pendingUnpaidOnline()
            ->whereNull('fee_reminder_sent_at')
            ->whereNotNull('fee_due_at')
            ->where('fee_due_at', '>', $now)
            ->with(['invoices', 'payments'])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $now) {
                foreach ($bookings as $booking) {
                    // Only nudge guest-initiated online-gateway bookings (see
                    // feeAutocancelEligible) — never a host-managed / manual one.
                    if (! $this->feeAutocancelEligible($booking)) {
                        continue;
                    }
                    // Nudge only once we're past the midpoint of the window so
                    // we don't double up on the invoice just sent at creation.
                    $created = Carbon::parse($booking->created_at);
                    $due = Carbon::parse($booking->fee_due_at);
                    $midpoint = $created->copy()->addSeconds((int) ($created->diffInSeconds($due) / 2));
                    if ($now->lt($midpoint)) {
                        continue;
                    }

                    $invoice = $booking->invoices
                        ->firstWhere('document_type', Invoice::TYPE_INVOICE);
                    $payUrl = $this->openDepositPayUrl($booking);

                    if ($invoice && $payUrl) {
                        SendBookingInvoice::dispatch($booking->id, $invoice->id, $payUrl);
                        $booking->forceFill(['fee_reminder_sent_at' => now()])->saveQuietly();
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * B. Auto-cancel pending online bookings whose booking fee went unpaid
     * past the pay window.
     */
    protected function cancelUnpaidFees(Carbon $now, CancelBooking $cancelBooking): int
    {
        $count = 0;

        $this->pendingUnpaidOnline()
            ->whereNotNull('fee_due_at')
            ->where('fee_due_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $cancelBooking) {
                foreach ($bookings as $booking) {
                    // Only guest-initiated online-gateway bookings auto-cancel.
                    // A manual booking, or one where the host merely attached a
                    // pay link via "send invoice", has no fee_autocancel flag —
                    // the host is collecting payment themselves, so leave it be.
                    if (! $this->feeAutocancelEligible($booking)) {
                        continue;
                    }
                    $cancelled = $cancelBooking->execute(
                        $booking,
                        __('Booking fee was not paid within the payment window.'),
                    );
                    if ($cancelled) {
                        $count++;
                        Log::info('Auto-cancelled unpaid booking fee', ['booking' => $booking->reference]);
                    }
                }
            });

        return $count;
    }

    /**
     * C. Remind confirmed bookings with an outstanding balance once they
     * reach the tenant's "X days before check-in" mark.
     */
    protected function remindUnpaidBalances(Carbon $today, CreateGatewayBill $createBill): int
    {
        $count = 0;

        Booking::withoutGlobalScopes()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('balance_paid_at')
            ->whereNull('full_payment_reminder_sent_at')
            ->whereNotNull('full_payment_reminder_at')
            ->whereDate('full_payment_reminder_at', '<=', $today)
            ->whereDate('check_out', '>=', $today)
            ->with(['property', 'tenant', 'guest', 'bookingGuests'])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $createBill) {
                foreach ($bookings as $booking) {
                    if ($booking->balanceDue() <= 0) {
                        // Nothing owed (e.g. paid in full already) — stamp so we
                        // stop re-evaluating it.
                        $booking->forceFill(['full_payment_reminder_sent_at' => now()])->saveQuietly();
                        continue;
                    }

                    $payUrl = $this->mintBalancePayUrl($booking, $createBill);

                    SendPaymentReminder::dispatch($booking->id, $payUrl);
                    $booking->forceFill(['full_payment_reminder_sent_at' => now()])->saveQuietly();
                    $count++;
                }
            });

        return $count;
    }

    /**
     * D. Auto-cancel confirmed bookings whose balance is still unpaid past
     * the tenant's balance deadline (due date or check-in day).
     */
    protected function cancelUnpaidBalances(Carbon $today, CancelBooking $cancelBooking): int
    {
        $count = 0;

        Booking::withoutGlobalScopes()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('balance_paid_at')
            // Never auto-cancel a stay that's already over — the guest may
            // well have stayed and the host simply never marked the balance
            // or checked them out. Only act on current/upcoming bookings.
            ->whereDate('check_out', '>=', $today)
            ->with(['tenant', 'property', 'bookingGuests', 'guest', 'payments'])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $today, $cancelBooking) {
                foreach ($bookings as $booking) {
                    if ($booking->balanceDue() <= 0) {
                        continue;
                    }

                    $tenant = $booking->tenant;
                    if (! $tenant) {
                        continue;
                    }

                    // Opt-in only. By default a deposit-paid booking is never
                    // auto-cancelled for an unpaid balance — homestay hosts
                    // typically collect the balance on arrival, so cancelling
                    // a paid reservation out from under them is destructive.
                    if (! $tenant->autoCancelUnpaidBalance()) {
                        continue;
                    }

                    $deadline = $tenant->cancelBalanceOn() === \App\Models\Tenant::CANCEL_BALANCE_DUE_DATE
                        ? ($booking->balance_due_at ? Carbon::parse($booking->balance_due_at)->startOfDay() : Carbon::parse($booking->check_in)->startOfDay())
                        : Carbon::parse($booking->check_in)->startOfDay();

                    if ($today->lt($deadline)) {
                        continue;
                    }

                    $cancelled = $cancelBooking->execute(
                        $booking,
                        __('Full payment was not completed before the deadline.'),
                    );
                    if ($cancelled) {
                        $count++;
                        Log::info('Auto-cancelled unpaid balance', ['booking' => $booking->reference]);
                    }
                }
            });

        return $count;
    }

    /**
     * Pending bookings with an unpaid booking fee that were created through
     * the online flow (i.e. a gateway deposit bill was issued). This is only a
     * COARSE prefilter — the definitive gate is the `meta.fee_autocancel` flag
     * (see feeAutocancelEligible), which is set ONLY by the guest online pay-now
     * flow. A host who later attaches a pay link to a manual/phone booking (via
     * "send invoice") also produces a gateway deposit payment, so the presence
     * of one alone must NOT arm auto-cancel — otherwise sending an invoice would
     * cancel the customer out from under the host.
     */
    protected function pendingUnpaidOnline()
    {
        return Booking::withoutGlobalScopes()
            ->where('status', Booking::STATUS_PENDING)
            ->whereNull('deposit_paid_at')
            ->whereHas('payments', fn ($q) => $q
                ->where('type', Payment::TYPE_DEPOSIT)
                ->whereIn('gateway_provider', ['toyyibpay', 'billplz', 'securepay']));
    }

    /**
     * Whether a booking may be auto-chased / auto-cancelled for an unpaid fee.
     * True only for guest-initiated online-gateway bookings (flagged at
     * creation in PublicBookingController). Manual bookings and host-attached
     * pay links never carry the flag, so the host stays in control of them.
     */
    protected function feeAutocancelEligible(Booking $booking): bool
    {
        return (bool) (is_array($booking->meta) ? ($booking->meta['fee_autocancel'] ?? false) : false);
    }

    /** Most recent open Toyyibpay deposit pay URL for a booking, if any. */
    protected function openDepositPayUrl(Booking $booking): ?string
    {
        $payment = $booking->payments
            ->where('type', Payment::TYPE_DEPOSIT)
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->sortByDesc('id')
            ->first();

        return is_array($payment?->meta) ? ($payment->meta['payment_url'] ?? null) : null;
    }

    /**
     * Mint (or reuse) a balance bill on the tenant's active gateway so the
     * reminder carries a one-tap pay link. Falls back to the signed guest-portal
     * URL when the tenant hasn't connected a gateway or the API call fails.
     */
    protected function mintBalancePayUrl(Booking $booking, CreateGatewayBill $createBill): ?string
    {
        $balance = $booking->balanceDue();
        if ($balance <= 0) {
            return null;
        }

        try {
            $result = $createBill->execute($booking, Payment::TYPE_BALANCE, $balance);
            return $result['payment_url'];
        } catch (PaymentGatewayException $e) {
            // Tenant hasn't set up a gateway (or creds rotated) — fall back to
            // the guest portal where they can see the booking + contact host.
            return $booking->guestPortalUrl();
        } catch (\Throwable $e) {
            report($e);
            return $booking->guestPortalUrl();
        }
    }
}
