<?php

namespace App\Actions\Operations;

use App\Models\Booking;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use Carbon\Carbon;
use Laravel\Pennant\Feature;

/**
 * Auto-schedule housekeeping for a confirmed booking, following the typical
 * Malaysian homestay routine:
 *
 *  1. POST-CHECKOUT TURNOVER — a full clean 30 min after the guest checks out
 *     (checkout 12:00pm → clean 12:30pm). Crew size + duration depend on the
 *     turnaround:
 *       • next guest arriving soon (≤ TURNOVER_WINDOW_DAYS) → 2 cleaners, 2h
 *         (rush so the next check-in is ready);
 *       • no imminent guest → 1 cleaner, 4h (thorough, no rush).
 *
 *  2. PRE-ARRIVAL DUSTING ("habuk") — if the house has sat idle for
 *     ≥ PRE_CLEAN_IDLE_DAYS before THIS booking's check-in, add a light
 *     pre-arrival clean a few hours before arrival. If a guest was in within
 *     PRE_CLEAN_IDLE_DAYS, the house is still fresh → skipped.
 *
 *  3. LAUNDRY — a linen batch after checkout (unchanged).
 *
 * Idempotent: keyed on (booking, type) via firstOrCreate, and never overwrites
 * a host-edited task. Gated by the `auto_operational_tasks` tier feature AND
 * the tenant's `auto_housekeeping` master toggle.
 */
class GenerateOperationalTasksForBooking
{
    /** Minutes after checkout the turnover clean starts. */
    private const POST_CLEAN_DELAY_MINUTES = 30;

    /** Next check-in within this many days of checkout ⇒ rush turnover. */
    private const TURNOVER_WINDOW_DAYS = 2;

    private const RUSH_CLEANERS = 2;
    private const RUSH_DURATION_MIN = 120;   // 2 hours

    private const RELAXED_CLEANERS = 1;
    private const RELAXED_DURATION_MIN = 240; // 4 hours

    /** House idle ≥ this many days before check-in ⇒ needs pre-arrival dusting. */
    private const PRE_CLEAN_IDLE_DAYS = 3;

    private const PRE_CLEAN_LEAD_HOURS = 3;   // start 3h before check-in time
    private const PRE_CLEAN_CLEANERS = 1;
    private const PRE_CLEAN_DURATION_MIN = 120; // 2 hours

    public function execute(Booking $booking): void
    {
        $booking->loadMissing('property', 'tenant');
        $tenant = $booking->tenant;

        if (! $tenant) {
            return;
        }

        // Scope the tier check to the booking's tenant explicitly — this action
        // also runs in the Toyyibpay webhook/return path where there's no tenant
        // context for Pennant's default (TenantContext-based) scope resolver.
        if (! Feature::for($tenant)->active('auto_operational_tasks')) {
            return;
        }

        if (! $tenant->autoHousekeepingEnabled()) {
            return;
        }

        $this->schedulePostCheckoutClean($booking);
        $this->scheduleLaundry($booking);
        $this->maybeSchedulePreArrivalClean($booking);
    }

    /**
     * Post-checkout full turnover, 30 min after checkout. Crew size + duration
     * scale to whether another guest is arriving soon on the same room.
     */
    private function schedulePostCheckoutClean(Booking $booking): void
    {
        $checkOut = $this->atTime($booking->check_out, $booking->property->check_out_time ?? '12:00');
        $scheduledAt = $checkOut->copy()->addMinutes(self::POST_CLEAN_DELAY_MINUTES);

        $next = $this->nextBookingOnRoom($booking);
        $hasImminentNext = $next !== null
            && Carbon::parse($booking->check_out)->startOfDay()
                ->diffInDays(Carbon::parse($next->check_in)->startOfDay()) <= self::TURNOVER_WINDOW_DAYS;

        if ($hasImminentNext) {
            $cleaners = self::RUSH_CLEANERS;
            $duration = self::RUSH_DURATION_MIN;
            $note = 'Auto-jadual: turnover cepat — tetamu seterusnya check-in '
                .Carbon::parse($next->check_in)->format('j M').'. '
                .self::RUSH_CLEANERS.' pencuci, ~2 jam.';
        } else {
            $cleaners = self::RELAXED_CLEANERS;
            $duration = self::RELAXED_DURATION_MIN;
            $note = 'Auto-jadual: tiada tetamu seterusnya — '
                .self::RELAXED_CLEANERS.' pencuci, ~4 jam (pembersihan menyeluruh).';
        }

        $task = CleaningTask::firstOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'property_id' => $booking->property_id,
                'room_id' => $booking->room_id,
                'booking_id' => $booking->id,
                'type' => CleaningTask::TYPE_FULL,
            ],
            [
                'status' => CleaningTask::STATUS_PENDING,
                'scheduled_at' => $scheduledAt,
                'cleaners_required' => $cleaners,
                'duration_minutes' => $duration,
                'auto_generated' => true,
                'notes' => $note,
            ],
        );

        // If it already existed as an auto task, keep the crew/duration current
        // with the latest turnaround picture (a later booking may have appeared
        // right after this checkout). Never touch a host-edited task.
        if (! $task->wasRecentlyCreated && $task->auto_generated && $task->status === CleaningTask::STATUS_PENDING) {
            $task->update([
                'cleaners_required' => $cleaners,
                'duration_minutes' => $duration,
                'notes' => $note,
            ]);
        }
    }

    /** Linen batch after checkout — pickup 2h after, expected back next day. */
    private function scheduleLaundry(Booking $booking): void
    {
        $checkOut = $this->atTime($booking->check_out, $booking->property->check_out_time ?? '12:00');

        LaundryTask::firstOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'property_id' => $booking->property_id,
                'booking_id' => $booking->id,
            ],
            [
                'status' => LaundryTask::STATUS_PENDING,
                'pickup_at' => $checkOut->copy()->addHours(2),
                'expected_return_at' => $checkOut->copy()->addDay(),
                'item_count' => max(2, (int) ($booking->adults ?? 1) * 2),
            ],
        );
    }

    /**
     * Pre-arrival dusting when the house has sat idle ≥ PRE_CLEAN_IDLE_DAYS
     * before this check-in. Skipped when a guest was in recently (still fresh).
     */
    private function maybeSchedulePreArrivalClean(Booking $booking): void
    {
        $prev = $this->previousBookingOnRoom($booking);

        // A prior guest within the window means the house is still clean.
        if ($prev !== null) {
            $idleDays = Carbon::parse($prev->check_out)->startOfDay()
                ->diffInDays(Carbon::parse($booking->check_in)->startOfDay());
            if ($idleDays < self::PRE_CLEAN_IDLE_DAYS) {
                return;
            }
        }
        // No prior booking at all ⇒ treat as idle (needs a dusting).

        $checkIn = $this->atTime($booking->check_in, $booking->property->check_in_time ?? '15:00');
        $scheduledAt = $checkIn->copy()->subHours(self::PRE_CLEAN_LEAD_HOURS);

        CleaningTask::firstOrCreate(
            [
                'tenant_id' => $booking->tenant_id,
                'property_id' => $booking->property_id,
                'room_id' => $booking->room_id,
                'booking_id' => $booking->id,
                'type' => CleaningTask::TYPE_PRE_ARRIVAL,
            ],
            [
                'status' => CleaningTask::STATUS_PENDING,
                'scheduled_at' => $scheduledAt,
                'cleaners_required' => self::PRE_CLEAN_CLEANERS,
                'duration_minutes' => self::PRE_CLEAN_DURATION_MIN,
                'auto_generated' => true,
                'notes' => 'Auto-jadual: pra-pembersihan (habuk) — rumah kosong sebelum ketibaan. '
                    .self::PRE_CLEAN_CLEANERS.' pencuci, ~2 jam sebelum check-in.',
            ],
        );
    }

    /**
     * The next active booking checking in on/after this booking's checkout, on
     * the same room. Cross-scope-safe (webhook path has no tenant context).
     */
    private function nextBookingOnRoom(Booking $booking): ?Booking
    {
        return Booking::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->where('room_id', $booking->room_id)
            ->where('id', '!=', $booking->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereDate('check_in', '>=', Carbon::parse($booking->check_out)->toDateString())
            ->orderBy('check_in')
            ->first();
    }

    /**
     * The most recent active booking checking out on/before this booking's
     * check-in, on the same room.
     */
    private function previousBookingOnRoom(Booking $booking): ?Booking
    {
        return Booking::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->where('room_id', $booking->room_id)
            ->where('id', '!=', $booking->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereDate('check_out', '<=', Carbon::parse($booking->check_in)->toDateString())
            ->orderByDesc('check_out')
            ->first();
    }

    /** Combine a date cast (midnight) with a wall-clock "HH:MM[:SS]" time. */
    private function atTime($date, string $time): Carbon
    {
        $parts = array_map('intval', array_pad(explode(':', $time), 3, 0));

        return Carbon::parse($date)->setTime($parts[0], $parts[1], $parts[2] ?? 0);
    }
}
