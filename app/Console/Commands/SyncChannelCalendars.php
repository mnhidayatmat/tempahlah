<?php

namespace App\Console\Commands;

use App\Models\ChannelIntegration;
use App\Services\Channels\ChannelCalendarSync;
use Illuminate\Console\Command;

/**
 * Poll every active OTA iCal import link (Airbnb / Booking.com) and reconcile
 * its reservations into CalendarBlock rows, so external bookings block
 * availability here and can't be double-booked. Cancellations (events that
 * vanished from the feed) are removed.
 *
 * Pro-only: skips any link whose tenant is not on a paid plan (the
 * ical_channel_sync feature = isPaid()). Scheduled hourly in routes/console.php.
 */
class SyncChannelCalendars extends Command
{
    protected $signature = 'channels:sync-ical
                            {--tenant= : Only sync links for this tenant id}
                            {--dry-run : List links that would sync, do nothing}';

    protected $description = 'Import Airbnb / Booking.com iCal calendars into availability blocks.';

    public function handle(ChannelCalendarSync $sync): int
    {
        $dry = (bool) $this->option('dry-run');
        $onlyTenant = $this->option('tenant');

        $links = ChannelIntegration::query()
            ->withoutGlobalScopes()
            ->where('mode', ChannelIntegration::MODE_ICAL)
            ->where('active', true)
            ->whereNotNull('ical_import_url')
            ->whereIn('channel', [ChannelIntegration::CHANNEL_AIRBNB, ChannelIntegration::CHANNEL_BOOKING])
            ->when($onlyTenant, fn ($q) => $q->where('tenant_id', $onlyTenant))
            ->with(['tenant', 'room'])
            ->get();

        $synced = 0;
        $skipped = 0;

        foreach ($links as $link) {
            $tenant = $link->tenant;

            // Pro gate: two-way channel sync is a paid feature. A stale link left
            // over from a lapsed Pro period must not keep syncing.
            if (! $tenant || ! $tenant->isPaid()) {
                $skipped++;
                continue;
            }

            if (! $link->room) {
                $skipped++;
                continue;
            }

            if ($dry) {
                $this->line("would sync: link #{$link->id} tenant {$tenant->id} {$link->channel} → room {$link->room->id}");
                continue;
            }

            $result = $sync->importLink($link);
            $synced++;

            $status = $result['ok'] ? 'ok' : 'ERROR';
            $this->line("link #{$link->id} {$link->channel} room {$link->room->id}: {$status} — {$result['message']}");
        }

        $this->info("Channel iCal sync done: {$synced} synced, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
