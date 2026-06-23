<?php

namespace App\Console\Commands;

use App\Jobs\SendCheckoutReminder;
use App\Models\Booking;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Find bookings whose checkout falls inside the next N hours (per-tenant lead
 * time, default 3h) and queue SendCheckoutReminder for each — an auto WhatsApp
 * with the host's checkout guidelines (clean up, take out rubbish, lock up…).
 *
 * Idempotent: bookings.checkout_reminder_sent_at gates re-fires.
 *
 * Scheduled hourly in routes/console.php.
 */
class DispatchCheckoutReminders extends Command
{
    protected $signature = 'wa:dispatch-checkout-reminders
                            {--dry-run : Print booking IDs that would be dispatched, do nothing}';

    protected $description = 'Queue pre-checkout reminders (WhatsApp) for bookings ending soon.';

    /**
     * The app runs in UTC (config/app.php), but property check-out times and the
     * host's sense of "now" are Malaysian local time (UTC+8). All lead-time math
     * MUST happen in this zone, otherwise "3 hours before check-out" lands 8
     * hours adrift — which previously fired reminders ~5pm, after the guest had
     * already checked out.
     */
    private const TZ = 'Asia/Kuala_Lumpur';

    public function handle(): int
    {
        $now = Carbon::now(self::TZ);
        $dry = (bool) $this->option('dry-run');

        // Cap the candidate window to the largest possible lead time so we
        // don't scan every future booking each pass. Per-tenant lead time is
        // re-checked precisely below.
        $maxHours = (int) (Tenant::query()->max('checkout_reminder_hours')
            ?: Tenant::CHECKOUT_REMINDER_DEFAULTS['hours']);
        $maxHours = max($maxHours, Tenant::CHECKOUT_REMINDER_DEFAULTS['hours']);
        $windowEnd = $now->copy()->addHours($maxHours + 1);

        $count = 0;
        Booking::query()
            ->withoutGlobalScopes()
            ->whereNull('checkout_reminder_sent_at')
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            ->whereBetween('check_out', [$now->toDateString(), $windowEnd->toDateString()])
            ->with('property', 'tenant')
            ->chunkById(100, function ($bookings) use (&$count, $dry, $now) {
                foreach ($bookings as $booking) {
                    $tenant = $booking->tenant;
                    if (! $tenant || ! $tenant->checkoutReminderEnabled()) {
                        continue;
                    }

                    $hoursBefore = $tenant->checkoutReminderHours();

                    // check_out is a `date` cast (a Carbon at 00:00) — format the
                    // date part before appending the property's check-out time,
                    // otherwise we'd build "2026-06-15 00:00:00 12:00:00".
                    $checkOutDate = Carbon::parse($booking->check_out)->format('Y-m-d');
                    $checkOutTime = substr((string) ($booking->property?->check_out_time ?? '12:00:00'), 0, 8);
                    $checkOutAt = Carbon::parse($checkOutDate.' '.$checkOutTime, self::TZ);

                    // Never remind once check-out has already passed.
                    if ($checkOutAt->lessThanOrEqualTo($now)) {
                        continue;
                    }

                    // Whole hours from now until check-out (positive — checkout
                    // is guaranteed to be in the future by the guard above).
                    $hoursToCheckOut = abs($now->diffInHours($checkOutAt, false));

                    // Fire only when we're inside [hoursBefore-1, hoursBefore].
                    if ($hoursToCheckOut > $hoursBefore || $hoursToCheckOut < ($hoursBefore - 1)) {
                        continue;
                    }

                    $this->line(($dry ? '[dry-run] ' : '').'-> '.$booking->reference
                        .' (tenant '.$booking->tenant_id.', checkout '.$checkOutAt->toDateTimeString().')');

                    if (! $dry) {
                        SendCheckoutReminder::dispatch($booking->id);
                    }
                    $count++;
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')."Dispatched {$count} checkout reminder(s).");

        return self::SUCCESS;
    }
}
