<?php

namespace App\Jobs;

use App\Models\TenantIntegration;
use App\Services\Calendar\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Delete a Google Calendar event by raw IDs, without needing the
 * originating Booking row to still exist.
 *
 * Used by BookingController::destroy() — the booking is hard-deleted in
 * a single transaction with all linked rows, so PushBookingToGoogleCalendar
 * (which loads by booking_id) wouldn't find anything when the worker runs.
 * The controller reads meta.google_event_id BEFORE the delete and queues
 * this job with the captured values.
 *
 * Silently skips when the tenant's GCal integration is gone or disabled
 * (e.g. they disconnected before the worker processed the queue).
 */
class DeleteGoogleCalendarEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 20;
    public int $backoff = 30;

    public function __construct(
        public int $tenantId,
        public string $eventId,
        public ?string $calendarId = null,
    ) {
        $this->onQueue('sync');
    }

    public function handle(GoogleCalendarService $google): void
    {
        $integration = TenantIntegration::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('provider', 'google_calendar')
            ->where('enabled', true)
            ->first();

        if (! $integration) {
            return;
        }

        // Honour the tenant's "write to calendar" toggle — when paused we
        // don't touch their calendar at all, including deletions.
        if (! $integration->gcalWriteEnabled()) {
            return;
        }

        try {
            $google->deleteEvent($integration, $this->eventId, $this->calendarId);
            $integration->update(['last_used_at' => now()]);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 401) {
                Log::warning('GoogleCalendar delete-by-id: token revoked', [
                    'tenant_id' => $this->tenantId,
                    'event_id'  => $this->eventId,
                ]);
                return;
            }
            throw $e;
        } catch (RequestException $e) {
            Log::warning('GoogleCalendar delete-by-id: HTTP error', [
                'tenant_id' => $this->tenantId,
                'event_id'  => $this->eventId,
                'status'    => $e->response?->status(),
                'body'      => substr((string) $e->response?->body(), 0, 500),
            ]);
            throw $e;
        }
    }
}
