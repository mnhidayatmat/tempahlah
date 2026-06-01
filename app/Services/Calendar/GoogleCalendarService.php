<?php

namespace App\Services\Calendar;

use App\Models\Booking;
use App\Models\TenantIntegration;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Calendar integration — platform-owned OAuth client.
 *
 * One OAuth app registered by Tempahlah. Each tenant goes through the consent
 * flow and we store their access_token + refresh_token in
 * tenant_integrations.config (encrypted at rest via the Eloquent cast).
 *
 * Phase 1 (this file): authorize URL, token exchange, userinfo fetch,
 * calendar list, revoke. Phase 3+ will add booking-push methods.
 */
class GoogleCalendarService
{
    protected const AUTH_BASE = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    protected const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    protected const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    /**
     * Build the URL we send the tenant to for Google's consent screen.
     *
     * `access_type=offline` + `prompt=consent` is REQUIRED to receive a
     * refresh_token. Without prompt=consent, Google only returns it the
     * very first time a user grants the scope — subsequent grants only
     * include the short-lived access_token, which would break our refresh
     * flow if the tenant ever reconnects.
     */
    public function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => config('services.google_calendar.client_id'),
            'redirect_uri'  => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope'         => implode(' ', config('services.google_calendar.scopes')),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
            'include_granted_scopes' => 'true',
        ]);

        return self::AUTH_BASE.'?'.$params;
    }

    /**
     * Exchange an authorization `code` for { access_token, refresh_token,
     * expires_in, scope, token_type, id_token }.
     *
     * Throws on HTTP error (4xx/5xx) — callback handler should catch and
     * show a friendly "Connection failed" page to the tenant.
     */
    public function exchangeCodeForTokens(string $code): array
    {
        return Http::asForm()
            ->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'redirect_uri'  => config('services.google_calendar.redirect_uri'),
                'grant_type'    => 'authorization_code',
            ])
            ->throw()
            ->json();
    }

    /**
     * Swap a refresh_token for a fresh access_token. Returns { access_token,
     * expires_in, scope, token_type } — note refresh_token is NOT re-issued
     * on a refresh call (the original one stays valid).
     *
     * If Google returns 400 with error=invalid_grant, the refresh_token has
     * been revoked (user clicked "remove access" in their Google account).
     * Caller should mark the integration as needing reconnect.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return Http::asForm()
            ->post(self::TOKEN_URL, [
                'refresh_token' => $refreshToken,
                'client_id'     => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'grant_type'    => 'refresh_token',
            ])
            ->throw()
            ->json();
    }

    /**
     * Fetch the authenticated user's email + name. Used so we can show the
     * tenant "Connected as wafa@gmail.com" rather than a faceless "Connected".
     */
    public function fetchUserInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();
    }

    /**
     * List the user's calendars so they can pick which one to sync to.
     * Returns array of { id, summary, primary?, accessRole, backgroundColor }.
     */
    public function listCalendars(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get(self::CALENDAR_API.'/users/me/calendarList', [
                'minAccessRole' => 'writer',
                'showHidden'    => 'false',
            ])
            ->throw()
            ->json();

        return $response['items'] ?? [];
    }

    /**
     * Best-effort revoke. Google accepts either an access_token or a
     * refresh_token. We always pass the refresh_token because it's the
     * long-lived one — revoking it cascades to invalidate active access
     * tokens too.
     *
     * Returns true if revoked (or already invalid — Google returns 400 in
     * that case which we treat as success). Logs and returns false on
     * unexpected failure but does NOT throw — disconnect should always
     * clear local state regardless.
     */
    public function revokeToken(string $token): bool
    {
        try {
            $response = Http::asForm()->post(self::REVOKE_URL, ['token' => $token]);
            if ($response->successful() || $response->status() === 400) {
                return true;
            }
            Log::warning('GoogleCalendar revoke unexpected status', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar revoke threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Create a brand-new calendar in the user's account. Used by the picker
     * when the tenant chooses "Create a new Tempahlah Bookings calendar".
     * Returns the new calendar object — { id, summary, timeZone, ... }.
     */
    public function createCalendar(string $accessToken, string $summary, string $timeZone = 'Asia/Kuala_Lumpur'): array
    {
        return Http::withToken($accessToken)
            ->post(self::CALENDAR_API.'/calendars', [
                'summary'  => $summary,
                'timeZone' => $timeZone,
            ])
            ->throw()
            ->json();
    }

    /**
     * Return a usable access_token for this integration, refreshing the
     * stored one if it's expired or about to expire. The new token + expiry
     * are persisted back to the TenantIntegration row.
     *
     * Concurrency safe — wraps refresh in a Cache::lock() so two simultaneous
     * sync jobs for the same tenant don't both hit Google's /token endpoint
     * with the same refresh_token. Second caller blocks up to 5s for the
     * first to finish, then re-reads from DB (so it picks up the freshly
     * refreshed token without doing a second exchange).
     *
     * Throws RuntimeException with code 401 ('invalid_grant') if the
     * refresh_token has been revoked (user removed access from their Google
     * account) — also marks the integration disabled with a user-facing
     * last_error so the next dashboard load shows the reconnect prompt.
     */
    public function freshAccessToken(TenantIntegration $integration): string
    {
        // Fast path — no lock needed if token is still valid (60s safety
        // margin so we don't hand back a token that expires mid-request).
        $config = $integration->config ?? [];
        if (($config['expires_at'] ?? 0) > now()->addSeconds(60)->timestamp
            && ! empty($config['access_token'])) {
            return $config['access_token'];
        }

        // Slow path: acquire a tenant-scoped lock before refreshing so a
        // burst of concurrent sync jobs doesn't all hit Google's /token at
        // once with the same refresh_token.
        $lockKey = 'google_calendar_refresh:tenant_'.$integration->tenant_id;
        $lock = Cache::lock($lockKey, 10);

        try {
            $lock->block(5);
        } catch (LockTimeoutException $e) {
            // Couldn't get the lock — another worker is mid-refresh. Re-read
            // the row from DB; they probably finished and wrote a new token.
            $integration->refresh();
            $config = $integration->config ?? [];
            if (($config['expires_at'] ?? 0) > now()->addSeconds(60)->timestamp
                && ! empty($config['access_token'])) {
                return $config['access_token'];
            }
            throw new \RuntimeException(
                'Google Calendar: could not acquire refresh lock and token still expired.', 0
            );
        }

        try {
            // Double-check inside the critical section. Another worker may
            // have refreshed while we were waiting for the lock.
            $integration->refresh();
            $config = $integration->config ?? [];
            if (($config['expires_at'] ?? 0) > now()->addSeconds(60)->timestamp
                && ! empty($config['access_token'])) {
                return $config['access_token'];
            }

            if (empty($config['refresh_token'])) {
                throw new \RuntimeException(
                    'Google Calendar: no refresh_token on file — tenant must reconnect.', 0
                );
            }

            try {
                $tokens = $this->refreshAccessToken($config['refresh_token']);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $body = (string) $e->response?->body();
                if (str_contains($body, 'invalid_grant')) {
                    $config['last_error'] = 'Google access was revoked. Please reconnect.';
                    $integration->config = $config;
                    $integration->enabled = false;
                    $integration->save();
                    throw new \RuntimeException('invalid_grant', 401, $e);
                }
                throw $e;
            }

            $config['access_token'] = $tokens['access_token'];
            $config['expires_at']   = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp;
            $config['last_error']   = null;
            $integration->config = $config;
            $integration->save();

            return $tokens['access_token'];
        } finally {
            $lock->release();
        }
    }

    /**
     * Update an existing Google Calendar event with the current booking
     * shape (dates, guest name, total, etc.). Used when a booking's
     * details change after creation.
     *
     * Returns Google's response (id, htmlLink, ...) on success. Returns
     * null if the integration isn't ready or the booking has no event
     * to update.
     */
    public function updateBooking(TenantIntegration $integration, Booking $booking): ?array
    {
        $config = $integration->config ?? [];
        $calendarId = $config['calendar_id'] ?? null;
        $eventId    = $booking->meta['google_event_id'] ?? null;

        if (! $calendarId || ! $eventId) {
            return null;
        }

        $accessToken = $this->freshAccessToken($integration);
        $event = $this->buildEventPayload($booking);

        return Http::withToken($accessToken)
            ->patch(self::CALENDAR_API.'/calendars/'.urlencode($calendarId).'/events/'.urlencode($eventId), $event)
            ->throw()
            ->json();
    }

    /**
     * Delete an event from the tenant's calendar. Used when a booking is
     * cancelled or hard-deleted.
     *
     * Treats 404 (not found) and 410 (already gone) as success — those
     * mean the event is no longer there, which is the desired end state.
     * 204 is the documented success status from Google.
     *
     * $calendarId override is for the hard-delete case where the booking
     * row is gone before the job runs — caller passes the cached
     * meta.google_calendar_id directly.
     */
    public function deleteEvent(TenantIntegration $integration, string $eventId, ?string $calendarId = null): bool
    {
        $calendarId = $calendarId ?: ($integration->config['calendar_id'] ?? null);
        if (! $calendarId) {
            return false;
        }

        $accessToken = $this->freshAccessToken($integration);

        $response = Http::withToken($accessToken)
            ->delete(self::CALENDAR_API.'/calendars/'.urlencode($calendarId).'/events/'.urlencode($eventId));

        if (in_array($response->status(), [204, 404, 410], true)) {
            return true;
        }

        $response->throw();
        return $response->successful();
    }

    /**
     * Push a booking as an ALL-DAY event into the tenant's chosen calendar.
     *
     * All-day intentional (not dateTime) — bookings are date-bound stays,
     * not appointments, and using `date` rather than `dateTime` avoids
     * timezone drift between server / Google / tenant's browser.
     *
     * Returns Google's event object (id, htmlLink, ...) on success, null
     * if the integration isn't ready. Throws on HTTP errors so the queue
     * can retry per the job's $tries policy.
     */
    public function pushBooking(TenantIntegration $integration, Booking $booking): ?array
    {
        $config = $integration->config ?? [];
        $calendarId = $config['calendar_id'] ?? null;
        if (! $calendarId) {
            Log::info('GoogleCalendar push: no calendar_id on integration', [
                'integration_id' => $integration->id,
            ]);
            return null;
        }

        // Auto-refresh access_token if expired.
        $accessToken = $this->freshAccessToken($integration);

        $event = $this->buildEventPayload($booking);

        $response = Http::withToken($accessToken)
            ->post(self::CALENDAR_API.'/calendars/'.urlencode($calendarId).'/events', $event)
            ->throw()
            ->json();

        return $response;
    }

    /**
     * Compose the Google Calendar event payload from a Booking. Kept as a
     * separate method so Phase 4 (update/cancel) can reuse the same shape.
     */
    protected function buildEventPayload(Booking $booking): array
    {
        $lead = $booking->bookingGuests?->where('is_lead', true)->first();
        $guestName = $lead?->full_name ?? $booking->guest?->name ?? __('Guest');
        $propertyName = $booking->property?->name ?? __('Property');

        // Title: "[Wafa Homestay] Aisha Rahman · #BK-12345"
        $summary = sprintf('[%s] %s · #%s', $propertyName, $guestName, $booking->reference);

        // Multi-line description with the key booking facts + a back-link.
        $nights = $booking->check_in->diffInDays($booking->check_out);
        $descLines = [
            sprintf('%s — %d %s', $propertyName, $nights, $nights === 1 ? 'night' : 'nights'),
            'Guest: '.$guestName,
        ];
        if ($lead?->phone) $descLines[] = 'Phone: '.$lead->phone;
        if ($lead?->email) $descLines[] = 'Email: '.$lead->email;

        $descLines[] = 'Guests: '.((int) $booking->adults).' adult'.($booking->adults > 1 ? 's' : '');
        if (! empty($booking->children)) {
            $descLines[] = '         '.((int) $booking->children).' child'.($booking->children > 1 ? 'ren' : '');
        }
        $descLines[] = 'Total: RM '.number_format((float) $booking->total_amount, 2);
        $descLines[] = 'Reference: '.$booking->reference;
        $descLines[] = '';
        $descLines[] = '—';
        $descLines[] = 'Auto-synced from Tempahlah.';
        $descLines[] = config('app.url').'/dashboard/bookings/'.$booking->id;

        return [
            'summary'     => $summary,
            'description' => implode("\n", $descLines),
            // All-day event — start.date INCLUSIVE, end.date EXCLUSIVE
            // (this is Google's contract for all-day events; matches how
            // hotel/Airbnb bookings work — check-out day not blocked).
            'start'       => ['date' => $booking->check_in->toDateString()],
            'end'         => ['date' => $booking->check_out->toDateString()],
            // Stamp our IDs so we can later look up "what booking was this
            // event for" in two-way sync (Phase 6 / v1.5).
            'extendedProperties' => [
                'private' => [
                    'tempahlah_booking_id'  => (string) $booking->id,
                    'tempahlah_booking_ref' => (string) $booking->reference,
                    'tempahlah_source'      => 'tempahlah',
                ],
            ],
            // Show the event as "busy" so other apps treating the calendar
            // as availability (e.g. iCal pulls) correctly see the block.
            'transparency' => 'opaque',
        ];
    }
}
