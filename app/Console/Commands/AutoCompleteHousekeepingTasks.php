<?php

namespace App\Console\Commands;

use App\Models\CleaningTask;
use App\Models\LaundryTask;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Auto-start + auto-complete housekeeping tasks the host forgot to tick, so
 * work that really happened doesn't sit "pending" forever and stays out of the
 * cost history / reports.
 *
 *  CLEANING — once the clock passes a task's scheduled window it is completed;
 *  while it's inside the window it's auto-started (in_progress). Window =
 *  scheduled_at → scheduled_at + duration (default 3h when the task carries no
 *  duration). On completion the tenant's typical cleaning cost is recorded if
 *  no cost was entered.
 *
 *  LAUNDRY — past its expected return (or pickup + 24h when none is set) the
 *  batch is marked returned; between pickup and return it is auto-picked-up.
 *  On return the tenant's typical laundry cost is recorded if none was entered.
 *
 * Gated per tenant by `Tenant::autoCompleteHousekeepingEnabled()` (its own
 * toggle, available on every plan). Explicit host-entered costs always win —
 * only a null cost is filled. Never touches completed/skipped/cancelled/
 * returned tasks.
 *
 * Times are the guest/host's Malaysian local wall-clock (Asia/Kuala_Lumpur) —
 * the same basis the rest of the booking + housekeeping lifecycle uses. The
 * stored datetimes carry MYT wall-clock face values, so the due-check
 * reinterprets each stored value in MYT before comparing (otherwise, with the
 * app running in UTC, a task would auto-complete ~8h off its intended time).
 * Scheduled hourly so a window's end is caught within the hour.
 */
class AutoCompleteHousekeepingTasks extends Command
{
    /** Assumed cleaning duration when a task carries none. */
    private const CLEAN_DEFAULT_DURATION_MIN = 180;

    /** Assumed laundry turnaround when a batch has no expected return. */
    private const LAUNDRY_DEFAULT_RETURN_HOURS = 24;

    protected $signature = 'housekeeping:auto-complete {--dry-run : List what would change without writing anything}';

    protected $description = 'Auto-start/complete cleaning + laundry tasks the host forgot to tick, recording the typical cost';

    public function handle(): int
    {
        $tz = config('homestay.timezone', 'Asia/Kuala_Lumpur');
        $now = Carbon::now($tz);
        $dryRun = (bool) $this->option('dry-run');

        // Reinterpret a stored datetime's face value as MYT wall-clock so the
        // comparison is against the real local time, not a UTC-shifted instant.
        $asLocal = fn (Carbon $dt): Carbon => Carbon::parse($dt->format('Y-m-d H:i:s'), $tz);

        $cleaning = $this->processCleaning($now, $asLocal, $dryRun);
        $laundry = $this->processLaundry($now, $asLocal, $dryRun);

        $this->info(($dryRun ? '[dry-run] ' : '')
            ."Cleaning: {$cleaning['started']} started, {$cleaning['completed']} completed · "
            ."Laundry: {$laundry['picked_up']} picked up, {$laundry['returned']} returned.");

        return self::SUCCESS;
    }

    /**
     * @return array{started:int, completed:int}
     */
    private function processCleaning(Carbon $now, callable $asLocal, bool $dryRun): array
    {
        $started = 0;
        $completed = 0;

        CleaningTask::withoutGlobalScopes()
            ->whereIn('status', [CleaningTask::STATUS_PENDING, CleaningTask::STATUS_IN_PROGRESS])
            ->whereNotNull('scheduled_at')
            // Coarse prefilter: only a task scheduled today or earlier (MYT) can
            // possibly be started/overdue. The precise window is checked below.
            ->whereDate('scheduled_at', '<=', $now->toDateString())
            ->with('tenant')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use (&$started, &$completed, $now, $asLocal, $dryRun) {
                foreach ($tasks as $task) {
                    $tenant = $task->tenant;
                    if (! $tenant?->autoCompleteHousekeepingEnabled()) {
                        continue;
                    }

                    $scheduled = $asLocal($task->scheduled_at);
                    $duration = $task->duration_minutes ?? self::CLEAN_DEFAULT_DURATION_MIN;
                    $windowEnd = $scheduled->copy()->addMinutes($duration);

                    if ($now->gte($windowEnd)) {
                        if ($dryRun) {
                            $this->line("Would COMPLETE cleaning #{$task->id} (scheduled {$scheduled->toDateTimeString()}, +{$duration}m)");
                            $completed++;

                            continue;
                        }
                        $task->status = CleaningTask::STATUS_COMPLETED;
                        $task->started_at = $task->started_at ?? $task->scheduled_at;
                        $task->completed_at = now();
                        $task->applyTypicalCostIfMissing($tenant);
                        $task->save();
                        $completed++;
                        Log::info('Auto-completed cleaning task', ['task' => $task->id, 'tenant' => $tenant->id, 'cost' => $task->cost]);
                    } elseif ($task->status === CleaningTask::STATUS_PENDING && $now->gte($scheduled)) {
                        if ($dryRun) {
                            $this->line("Would START cleaning #{$task->id} (scheduled {$scheduled->toDateTimeString()})");
                            $started++;

                            continue;
                        }
                        $task->status = CleaningTask::STATUS_IN_PROGRESS;
                        $task->started_at = $task->started_at ?? $task->scheduled_at;
                        $task->save();
                        $started++;
                    }
                }
            });

        return ['started' => $started, 'completed' => $completed];
    }

    /**
     * @return array{picked_up:int, returned:int}
     */
    private function processLaundry(Carbon $now, callable $asLocal, bool $dryRun): array
    {
        $pickedUp = 0;
        $returned = 0;

        LaundryTask::withoutGlobalScopes()
            ->whereIn('status', [LaundryTask::STATUS_PENDING, LaundryTask::STATUS_PICKED_UP])
            ->whereNotNull('pickup_at')
            ->whereDate('pickup_at', '<=', $now->toDateString())
            ->with('tenant')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use (&$pickedUp, &$returned, $now, $asLocal, $dryRun) {
                foreach ($tasks as $task) {
                    $tenant = $task->tenant;
                    if (! $tenant?->autoCompleteHousekeepingEnabled()) {
                        continue;
                    }

                    $pickup = $asLocal($task->pickup_at);
                    $returnAt = $task->expected_return_at
                        ? $asLocal($task->expected_return_at)
                        : $pickup->copy()->addHours(self::LAUNDRY_DEFAULT_RETURN_HOURS);

                    if ($now->gte($returnAt)) {
                        if ($dryRun) {
                            $this->line("Would RETURN laundry #{$task->id} (return {$returnAt->toDateTimeString()})");
                            $returned++;

                            continue;
                        }
                        $task->status = LaundryTask::STATUS_RETURNED;
                        $task->picked_up_at = $task->picked_up_at ?? $task->pickup_at;
                        $task->returned_at = now();
                        $task->applyTypicalCostIfMissing($tenant);
                        $task->save();
                        $returned++;
                        Log::info('Auto-returned laundry task', ['task' => $task->id, 'tenant' => $tenant->id, 'cost' => $task->cost]);
                    } elseif ($task->status === LaundryTask::STATUS_PENDING && $now->gte($pickup)) {
                        if ($dryRun) {
                            $this->line("Would PICK UP laundry #{$task->id} (pickup {$pickup->toDateTimeString()})");
                            $pickedUp++;

                            continue;
                        }
                        $task->status = LaundryTask::STATUS_PICKED_UP;
                        $task->picked_up_at = $task->picked_up_at ?? $task->pickup_at;
                        $task->save();
                        $pickedUp++;
                    }
                }
            });

        return ['picked_up' => $pickedUp, 'returned' => $returned];
    }
}
