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
 * Push a confirmed booking into the tenant's connected Google Calendar.
 *
 * Dispatched from the two booking-confirmation sites
 * (ToyyibpayWebhookController + BookingController@markPaid) in parallel with
 * SendBookingConfirmation. Silently no-ops when the tenant hasn't connected
 * Google Calendar — safe to fire unconditionally.
 *
 * Idempotent — re-running for the same booking is a no-op if
 * meta.google_event_id is already populated.
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
        // request, so the BelongsToTenant scope would filter this out.
        $booking = Booking::withoutGlobalScopes()
            ->with(['bookingGuests', 'property:id,name', 'guest:id,name,email,phone'])
            ->find($this->bookingId);

        if (! $booking) {
            return;
        }

        // Idempotency check — already pushed.
        $meta = $booking->meta ?? [];
        if (! empty($meta['google_event_id'])) {
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
            // silently skip, this is expected for most tenants on free tier.
            return;
        }

        try {
            $event = $google->pushBooking($integration, $booking);
        } catch (\RuntimeException $e) {
            // freshAccessToken throws RuntimeException(401, 'invalid_grant')
            // when the refresh_token was revoked. It already flipped the
            // integration to disabled + set last_error, so don't retry.
            if ($e->getCode() === 401) {
                Log::warning('GoogleCalendar push: token revoked, integration disabled', [
                    'tenant_id'  => $booking->tenant_id,
                    'booking_id' => $booking->id,
                ]);
                return;
            }
            throw $e;
        } catch (RequestException $e) {
            // Surface upstream HTTP errors with the response body so we can
            // diagnose 403 calendar-not-found, 410 deleted, etc. without
            // tailing the logs character by character.
            Log::warning('GoogleCalendar push: HTTP error', [
                'tenant_id'  => $booking->tenant_id,
                'booking_id' => $booking->id,
                'status'     => $e->response?->status(),
                'body'       => substr((string) $e->response?->body(), 0, 500),
            ]);
            throw $e;
        }

        if (! $event || empty($event['id'])) {
            return;
        }

        // Stamp the event id back onto the booking for idempotency +
        // future PATCH/DELETE (Phase 4).
        $meta['google_event_id']        = $event['id'];
        $meta['google_event_html_link'] = $event['htmlLink'] ?? null;
        $meta['google_event_synced_at'] = now()->toIso8601String();
        $meta['google_calendar_id']     = $integration->config['calendar_id'];

        $booking->meta = $meta;
        $booking->saveQuietly();

        // Track integration usage so the dashboard can show "last used".
        $integration->update(['last_used_at' => now()]);
    }
}
