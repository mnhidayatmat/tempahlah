<?php

namespace App\Console\Commands;

use App\Actions\Booking\CheckOutBooking;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auto-checks-out any booking the host never manually checked out: once the
 * clock is 24 hours past the property's scheduled check-out time, a still-active
 * (confirmed / checked_in) booking is transitioned to checked_out. A full day's
 * grace gives the host time to check out manually first and guarantees the guest
 * has left (matches the "completed after check-out date" convention OTAs use).
 *
 * Runs through the shared CheckOutBooking action, so an auto-checkout does
 * exactly what the manual button does — stamps the timestamps, prepares the
 * pending deposit refund, and fires the post-checkout testimonial request: a
 * "thank you for staying" email + WhatsApp carrying the signed testimonial-form
 * link (sent at most once, deduped by review_requested_at). So the guest is
 * thanked and asked to review whether the host clicked "Check out guest" or the
 * system did it for them.
 *
 * Times are the guest's Malaysian local check-out moment (Asia/Kuala_Lumpur),
 * the same basis the rest of the booking lifecycle uses. Scheduled hourly so
 * the 24-hour mark is caught within the hour regardless of check-out time.
 */
class AutoCheckoutBookings extends Command
{
    /** Grace period after the scheduled check-out time before auto-checkout. */
    private const HOURS_AFTER_CHECKOUT = 24;

    protected $signature = 'bookings:auto-checkout {--dry-run : List what would be checked out without changing anything}';

    protected $description = 'Auto-check-out active bookings 24 hours after their check-out time and request a testimonial';

    public function handle(CheckOutBooking $checkOutBooking): int
    {
        $tz = config('homestay.timezone', 'Asia/Kuala_Lumpur');
        $now = Carbon::now($tz);
        $dryRun = (bool) $this->option('dry-run');

        $count = 0;

        Booking::withoutGlobalScopes()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            // Coarse prefilter: only a stay whose check-out date is today or
            // earlier can possibly be 24h past check-out. The precise cutoff
            // (check-out time + 24h, per the property) is applied below.
            ->whereDate('check_out', '<=', $now->toDateString())
            ->with(['property', 'payments'])
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $checkOutBooking, $dryRun, $now, $tz) {
                foreach ($bookings as $booking) {
                    // check_out is a `date` cast (Carbon at 00:00) — format the
                    // date part before appending the property's check-out time,
                    // otherwise we'd build "2026-07-12 00:00:00 12:00:00".
                    $checkOutDate = Carbon::parse($booking->check_out)->format('Y-m-d');
                    $checkOutTime = substr((string) ($booking->property?->check_out_time ?? '12:00:00'), 0, 8);
                    $cutoff = Carbon::parse($checkOutDate.' '.$checkOutTime, $tz)
                        ->addHours(self::HOURS_AFTER_CHECKOUT);

                    // Not yet 24h past this booking's check-out — leave it.
                    if ($now->lessThan($cutoff)) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would auto-checkout {$booking->reference} "
                            . "(checkout {$checkOutDate} {$checkOutTime}, +24h cutoff {$cutoff->toDateTimeString()}, status {$booking->status})");
                        $count++;
                        continue;
                    }

                    if ($checkOutBooking->execute($booking)) {
                        $count++;
                        Log::info('Auto-checked-out booking 24h past check-out', [
                            'booking'   => $booking->reference,
                            'check_out' => $checkOutDate.' '.$checkOutTime,
                        ]);
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')
            . "Auto-checked-out {$count} booking(s) (24h after check-out).");

        return self::SUCCESS;
    }
}
