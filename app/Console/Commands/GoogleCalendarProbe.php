<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Calendar\GoogleCalendarService;
use Illuminate\Console\Command;

/**
 * php artisan gcal:probe {tenant} [--force-refresh] [--sync-latest] [--expire-now]
 *
 * Operations command for inspecting + exercising a tenant's Google Calendar
 * integration on prod via SSH. Handy when a tenant reports "my bookings
 * aren't appearing" or "I need to re-test the refresh flow".
 *
 * Examples:
 *   php artisan gcal:probe 1                       # show state only
 *   php artisan gcal:probe wafahomestay            # by slug
 *   php artisan gcal:probe 1 --force-refresh       # force token refresh
 *   php artisan gcal:probe 1 --sync-latest         # also dispatch a sync job
 *                                                    for the latest booking
 *   php artisan gcal:probe 1 --expire-now          # rewrite expires_at into
 *                                                    the past, then probe —
 *                                                    proves the lock + refresh
 *                                                    path works end-to-end
 */
class GoogleCalendarProbe extends Command
{
    protected $signature = 'gcal:probe
                            {tenant : Tenant ID or slug}
                            {--force-refresh : Force a token refresh even if still valid}
                            {--sync-latest : Also dispatch PushBookingToGoogleCalendar for the tenant\'s most recent booking}
                            {--expire-now : Backdate expires_at to 1 minute ago before refreshing (proves refresh works)}';

    protected $description = 'Inspect + exercise a tenant\'s Google Calendar integration.';

    public function handle(GoogleCalendarService $google): int
    {
        $arg = $this->argument('tenant');
        $tenant = is_numeric($arg)
            ? Tenant::withoutGlobalScopes()->find((int) $arg)
            : Tenant::withoutGlobalScopes()->where('slug', $arg)->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$arg}");
            return self::FAILURE;
        }

        $this->line("");
        $this->info("Tenant #{$tenant->id} · {$tenant->business_name} ({$tenant->slug})");
        $this->line(str_repeat('─', 64));

        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'google_calendar')
            ->first();

        if (! $integration) {
            $this->warn("No google_calendar integration row.");
            return self::SUCCESS;
        }

        $config = $integration->config ?? [];
        $this->line("Enabled:        " . ($integration->enabled ? 'yes' : 'NO'));
        $this->line("Connected at:   " . ($integration->connected_at?->toDateTimeString() ?? '—'));
        $this->line("Last used at:   " . ($integration->last_used_at?->toDateTimeString() ?? '—'));
        $this->line("Google email:   " . ($config['google_email'] ?? '—'));
        $this->line("Calendar:       " . ($config['calendar_name'] ?? '—') . " (id: " . ($config['calendar_id'] ?? '—') . ")");
        $this->line("access_token:   " . (! empty($config['access_token']) ? substr($config['access_token'], 0, 16) . '…' : '— MISSING'));
        $this->line("refresh_token:  " . (! empty($config['refresh_token']) ? substr($config['refresh_token'], 0, 16) . '…' : '— MISSING'));

        $expiresAt = (int) ($config['expires_at'] ?? 0);
        if ($expiresAt > 0) {
            $when = now()->createFromTimestamp($expiresAt);
            $diff = $when->diffForHumans();
            $status = $expiresAt > now()->timestamp ? "valid" : "EXPIRED";
            $this->line("expires_at:     {$when->toDateTimeString()} ({$diff}, {$status})");
        } else {
            $this->line("expires_at:     —");
        }

        if (! empty($config['last_error'])) {
            $this->error("last_error:     {$config['last_error']}");
        }

        // --expire-now: backdate the expiry so the refresh path is exercised.
        if ($this->option('expire-now')) {
            $this->line("");
            $this->warn("→ --expire-now: backdating expires_at to 60s ago");
            $config['expires_at'] = now()->subMinute()->timestamp;
            $integration->config = $config;
            $integration->save();
        }

        // --force-refresh or --expire-now: walk the refresh path.
        if ($this->option('force-refresh') || $this->option('expire-now')) {
            $this->line("");
            $this->info("→ Calling freshAccessToken() …");
            try {
                $token = $google->freshAccessToken($integration);
                $integration->refresh();
                $config = $integration->config ?? [];
                $newExp = now()->createFromTimestamp((int) ($config['expires_at'] ?? 0));
                $this->info("  ✓ refreshed — new token {$token[0]}{$token[1]}…{$token[2]}{$token[3]} valid until {$newExp->toDateTimeString()} ({$newExp->diffForHumans()})");
            } catch (\RuntimeException $e) {
                $this->error("  ✗ refresh failed [{$e->getCode()}]: " . $e->getMessage());
                return self::FAILURE;
            } catch (\Throwable $e) {
                $this->error("  ✗ refresh threw: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        // --sync-latest: dispatch a sync job for the most recent booking.
        if ($this->option('sync-latest')) {
            $this->line("");
            $booking = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->first();

            if (! $booking) {
                $this->warn("→ --sync-latest: no bookings for this tenant");
            } else {
                $this->info("→ Dispatching PushBookingToGoogleCalendar for booking #{$booking->id} (ref {$booking->reference}, status {$booking->status})");
                \App\Jobs\PushBookingToGoogleCalendar::dispatch($booking->id);
                $this->line("  ✓ queued — tail /var/log/supervisor/tempahlah-queue.log to watch it run");
            }
        }

        $this->line("");
        return self::SUCCESS;
    }
}
