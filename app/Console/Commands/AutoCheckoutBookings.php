<?php

namespace App\Console\Commands;

use App\Actions\Booking\CheckOutBooking;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auto-checks-out any booking the host never manually checked out: once the
 * calendar has moved 1 full day past the check-out date, a still-active
 * (confirmed / checked_in) booking is transitioned to checked_out.
 *
 * Runs through the shared CheckOutBooking action, so an auto-checkout does
 * exactly what the manual button does — stamps the timestamps, prepares the
 * pending deposit Refund, and fires the post-checkout testimonial request
 * (email + WhatsApp). So a guest gets their "leave a testimonial" link whether
 * the host clicked "Check out guest" or the system did it for them.
 *
 * Dates are the guest's Malaysian local checkout day (Asia/Kuala_Lumpur), the
 * same basis the rest of the booking lifecycle uses.
 */
class AutoCheckoutBookings extends Command
{
    private const TZ = 'Asia/Kuala_Lumpur';

    protected $signature = 'bookings:auto-checkout {--dry-run : List what would be checked out without changing anything}';

    protected $description = 'Auto-check-out active bookings 1 day after their check-out date and request a testimonial';

    public function handle(CheckOutBooking $checkOutBooking): int
    {
        // "1 day after the check-out date": on 12 Jul, act on any stay whose
        // check-out was 11 Jul or earlier. i.e. check_out <= today − 1 day.
        $cutoff = Carbon::now(self::TZ)->startOfDay()->subDay()->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $count = 0;

        Booking::withoutGlobalScopes()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            ->whereDate('check_out', '<=', $cutoff)
            ->with('payments')
            ->orderBy('id')
            ->chunkById(200, function ($bookings) use (&$count, $checkOutBooking, $dryRun) {
                foreach ($bookings as $booking) {
                    if ($dryRun) {
                        $this->line("Would auto-checkout {$booking->reference} (check_out {$booking->check_out->toDateString()}, status {$booking->status})");
                        $count++;
                        continue;
                    }

                    if ($checkOutBooking->execute($booking)) {
                        $count++;
                        Log::info('Auto-checked-out booking past its check-out date', [
                            'booking'  => $booking->reference,
                            'check_out' => $booking->check_out->toDateString(),
                        ]);
                    }
                }
            });

        $this->info($dryRun
            ? "{$count} booking(s) would be auto-checked-out (cutoff {$cutoff})."
            : "Auto-checked-out {$count} booking(s) (cutoff {$cutoff}).");

        return self::SUCCESS;
    }
}
