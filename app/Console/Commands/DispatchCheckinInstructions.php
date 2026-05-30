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

    public function handle(): int
    {
        $now = Carbon::now();
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

                    $checkInAt = Carbon::parse($booking->check_in.' '
                        .(string) ($booking->property?->check_in_time ?? '15:00:00'));

                    $hoursToCheckIn = $now->diffInHours($checkInAt, false);

                    // Fire when we're inside [hoursBefore-1, hoursBefore) of check-in.
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
