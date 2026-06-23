<?php

namespace App\Console\Commands;

use App\Jobs\SendCheckInInstructions;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Find bookings whose check-in falls inside the next N hours (per tenant
 * preference, default 24h) and dispatch SendCheckInInstructions for each.
 *
 * Idempotent: bookings.checkin_instructions_sent_at gates re-fires.
 *
 * Scheduled hourly in routes/console.php.
 */
class DispatchCheckinInstructions extends Command
{
    protected $signature = 'wa:dispatch-checkin-instructions
                            {--hours=24 : Default lead time in hours, overridden per-tenant pref}
                            {--dry-run : Print booking IDs that would be dispatched, do nothing}';

    protected $description = 'Queue check-in instructions (email + WhatsApp) for bookings starting soon.';

    /**
     * The app runs in UTC (config/app.php) but property check-in times and the
     * host's sense of "now" are Malaysian local time (UTC+8). Do the lead-time
     * math in this zone so "N hours before check-in" isn't 8 hours adrift.
     */
    private const TZ = 'Asia/Kuala_Lumpur';

    public function handle(): int
    {
        $now = Carbon::now(self::TZ);
        $defaultHours = (int) $this->option('hours');
        $dry = (bool) $this->option('dry-run');

        // We use a small window per pass (the cron should run hourly) so a
        // booking 24h away gets picked up on the 24h pass +/- 1h, not every
        // single hour from 24h down to 0h.
        $windowStart = $now->copy();
        $windowEnd   = $now->copy()->addHours($defaultHours + 1);

        $count = 0;
        Booking::query()
            ->withoutGlobalScopes()
            ->whereNull('checkin_instructions_sent_at')
            ->whereIn('status', [Booking::STATUS_CONFIRMED])
            ->whereBetween('check_in', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->with('property', 'tenant')
            ->chunkById(100, function ($bookings) use (&$count, $dry, $defaultHours, $now) {
                foreach ($bookings as $booking) {
                    // Resolve effective hours-before for this tenant.
                    $effective = $defaultHours;
                    if ($session = $booking->tenant?->whatsappSession) {
                        $effective = (int) ($session->pref('checkin_hours_before') ?? $defaultHours);
                    }

                    // check_in is a `date` cast (a Carbon at 00:00) — format the
                    // date part before appending the property's check-in time,
                    // otherwise we'd build "2026-06-15 00:00:00 15:00:00".
                    $checkInDate = Carbon::parse($booking->check_in)->format('Y-m-d');
                    $checkInTime = substr((string) ($booking->property?->check_in_time ?? '15:00:00'), 0, 8);
                    $checkInAt = Carbon::parse($checkInDate.' '.$checkInTime, self::TZ);

                    // Never send check-in instructions once check-in has passed.
                    if ($checkInAt->lessThanOrEqualTo($now)) {
                        continue;
                    }

                    // Whole hours from now until check-in (positive — guaranteed
                    // to be in the future by the guard above).
                    $hoursToCheckIn = abs($now->diffInHours($checkInAt, false));

                    // Fire only when we're inside [hoursBefore-1, hoursBefore].
                    if ($hoursToCheckIn > $effective || $hoursToCheckIn < ($effective - 1)) {
                        continue;
                    }

                    $this->line(($dry ? '[dry-run] ' : '').'-> '.$booking->reference
                        .' (tenant '.$booking->tenant_id.', check-in '.$checkInAt->toDateTimeString().')');

                    if (! $dry) {
                        SendCheckInInstructions::dispatch($booking->id);
                    }
                    $count++;
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')."Dispatched {$count} check-in instruction(s).");
        return self::SUCCESS;
    }
}
