<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\TenantIntegration;
use App\Services\Calendar\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Sync a booking to the tenant's connected Google Calendar.
 *
 * Single entry point for the entire booking lifecycle — the job inspects
 * the booking's current state + meta.google_event_id to decide which
 * action to take:
 *
 *   active status + no event → CREATE  (calendar.events.insert)
 *   active status + has event → UPDATE (calendar.events.patch)
 *   inactive status + has event → DELETE (calendar.events.delete)
 *   inactive status + no event → no-op
 *
 * "Active" = anything other than cancelled / no_show. Soft-cancellations
 * thus remove the calendar block; reactivating a cancelled booking
 * (manual ops only) would re-create it on next dispatch.
 *
 * Hard-deleted bookings can't go through this job (the row is gone before
 * the worker runs) — see DeleteGoogleCalendarEvent for that path.
 *
 * Tenants without GCal connected are silently skipped — safe to fire
 * from every booking transition regardless of tenant tier.
 *
 * All operations idempotent: meta.google_event_id is updated atomically
 * after each successful Google call.
 */
class PushBookingToGoogleCalendar implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 30;

    public function __construct(public int $bookingId)
    {
        // Illuminate\Bus\Queueable::$queue has no default — re-declaring as
        // a property in the subclass is a fatal trait conflict (per
        // CLAUDE.md gotcha). Always set via onQueue() in __construct().
        $this->onQueue('sync');
    }

    public function handle(GoogleCalendarService $google): void
    {
        // withoutGlobalScopes — queue worker doesn't run inside a tenant
        // request, so BelongsToTenant scope would filter this out.
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property:id,name', 'guest:id,name,email,phone'])
            ->find($this->bookingId);

        if (! $booking) {
            return;
        }

        // Locate the tenant's google_calendar integration.
        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $booking->tenant_id)
            ->where('provider', 'google_calendar')
            ->where('enabled', true)
            ->first();

        if (! $integration
            || empty($integration->config['access_token'])
            || empty($integration->config['calendar_id'])) {
            // Tenant hasn't connected GCal or hasn't picked a calendar —
            // silently skip, this is expected for most tenants.
            return;
        }

        // Tenant can pause writes from Settings without disconnecting — keeps
        // the calendar choice + tokens, just stops Tempahlah pushing events.
        if (! $integration->gcalWriteEnabled()) {
            return;
        }

        $meta = $booking->meta ?? [];
        $hasEvent = ! empty($meta['google_event_id']);
        $isActive = ! in_array($booking->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_NO_SHOW,
        ], true);

        try {
            if ($isActive && ! $hasEvent) {
                $this->doCreate($google, $integration, $booking, $meta);
            } elseif ($isActive && $hasEvent) {
                $this->doUpdate($google, $integration, $booking, $meta);
            } elseif (! $isActive && $hasEvent) {
                $this->doDelete($google, $integration, $booking, $meta);
            }
            // else: no event + inactive = nothing to do.

            $integration->update(['last_used_at' => now()]);
        } catch (\RuntimeException $e) {
            // freshAccessToken throws RuntimeException(401) when the
            // refresh_token was revoked. It already disabled the
            // integration with a last_error — don't retry.
            if ($e->getCode() === 401) {
                Log::warning('GoogleCalendar sync: token revoked, integration disabled', [
                    'tenant_id'  => $booking->tenant_id,
                    'booking_id' => $booking->id,
                ]);
                return;
            }
            throw $e;
        } catch (RequestException $e) {
            Log::warning('GoogleCalendar sync: HTTP error', [
                'tenant_id'  => $booking->tenant_id,
                'booking_id' => $booking->id,
                'status'     => $e->response?->status(),
                'body'       => substr((string) $e->response?->body(), 0, 500),
            ]);
            throw $e;
        }
    }

    protected function doCreate(GoogleCalendarService $google, TenantIntegration $integration, Booking $booking, array $meta): void
    {
        $event = $google->pushBooking($integration, $booking);
        if (! $event || empty($event['id'])) {
            return;
        }

        $meta['google_event_id']        = $event['id'];
        $meta['google_event_html_link'] = $event['htmlLink'] ?? null;
        $meta['google_event_synced_at'] = now()->toIso8601String();
        $meta['google_calendar_id']     = $integration->config['calendar_id'];

        $booking->meta = $meta;
        $booking->saveQuietly();
    }

    protected function doUpdate(GoogleCalendarService $google, TenantIntegration $integration, Booking $booking, array $meta): void
    {
        $event = $google->updateBooking($integration, $booking);
        if (! $event) {
            return;
        }

        $meta['google_event_html_link'] = $event['htmlLink'] ?? $meta['google_event_html_link'] ?? null;
        $meta['google_event_synced_at'] = now()->toIso8601String();

        $booking->meta = $meta;
        $booking->saveQuietly();
    }

    protected function doDelete(GoogleCalendarService $google, TenantIntegration $integration, Booking $booking, array $meta): void
    {
        $google->deleteEvent(
            $integration,
            $meta['google_event_id'],
            $meta['google_calendar_id'] ?? null,
        );

        // Drop the event pointers but keep a deletion timestamp for audit.
        // If the booking is ever re-activated, the next sync will re-create
        // a fresh event (no stale id around).
        $meta['google_event_id']         = null;
        $meta['google_event_html_link']  = null;
        $meta['google_event_deleted_at'] = now()->toIso8601String();

        $booking->meta = $meta;
        $booking->saveQuietly();
    }
}
