<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\TenantIntegration;
use App\Services\Calendar\GoogleCalendarService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Google Calendar OAuth dance — Phase 1.
 *
 * Tenant clicks "Connect Google Calendar" → start() → Google consent →
 * callback() stores tokens → redirect back to the integration page.
 *
 * Phase 2 will add the calendar-picker step. For now we auto-default to
 * the user's "primary" calendar so the connect flow completes end-to-end
 * in one redirect chain.
 */
class GoogleCalendarOAuthController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    /**
     * Build the consent URL and redirect.
     *
     * We bind the OAuth `state` to the current tenant ID + session ID
     * via HMAC signature with 10-minute expiry. This prevents:
     *   - CSRF (attacker can't forge a callback for another tenant)
     *   - replay (state expires)
     *   - tenant swap (state is tied to current session)
     */
    public function start(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        $state = $this->signState([
            'tenant_id'  => $tenant->id,
            'session_id' => $request->session()->getId(),
            'nonce'      => Str::random(16),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($this->google->authorizeUrl($state));
    }

    /**
     * Handle Google's redirect back to us.
     *
     * Possible inbound shapes:
     *   ?code=xxx&state=xxx              → success path
     *   ?error=access_denied&state=xxx   → user clicked "Cancel" on consent
     *   missing code/error               → malformed, treat as error
     */
    public function callback(Request $request): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);

        // User cancelled on the Google consent screen — friendly path back.
        if ($request->filled('error')) {
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection cancelled.'));
        }

        $code = $request->query('code');
        $state = $request->query('state');

        if (! $code || ! $state) {
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed: missing code.'));
        }

        // Verify HMAC state — covers CSRF, replay, and tenant-swap attempts.
        $payload = $this->verifyState($state);
        if (! $payload) {
            Log::warning('GoogleCalendar OAuth: invalid state', [
                'tenant_id' => $tenant->id,
                'state'     => substr($state, 0, 12).'…',
            ]);
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed: invalid state. Please try again.'));
        }

        if ((int) $payload['tenant_id'] !== (int) $tenant->id) {
            Log::warning('GoogleCalendar OAuth: tenant mismatch', [
                'expected_tenant_id' => $payload['tenant_id'],
                'actual_tenant_id'   => $tenant->id,
            ]);
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed: session mismatch.'));
        }

        if ($payload['session_id'] !== $request->session()->getId()) {
            Log::warning('GoogleCalendar OAuth: session mismatch', ['tenant_id' => $tenant->id]);
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed: session changed. Please try again.'));
        }

        // Exchange code for tokens.
        try {
            $tokens = $this->google->exchangeCodeForTokens($code);
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendar OAuth: token exchange failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed during token exchange.'));
        }

        if (empty($tokens['access_token'])) {
            return redirect()
                ->route('tenant.integrations.show', 'google_calendar')
                ->with('status', __('Google connection failed: no access token returned.'));
        }

        // Fetch the user's email so we can display "Connected as ..." in the UI.
        $email = null;
        $name = null;
        try {
            $userInfo = $this->google->fetchUserInfo($tokens['access_token']);
            $email = $userInfo['email'] ?? null;
            $name  = $userInfo['name'] ?? null;
        } catch (\Throwable $e) {
            // Non-fatal — we can still operate without showing the email.
            Log::info('GoogleCalendar OAuth: userinfo fetch failed (non-fatal)', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        }

        $integration = TenantIntegration::firstOrNew([
            'provider' => 'google_calendar',
        ], [
            'tenant_id' => $tenant->id,
        ]);
        $integration->tenant_id = $tenant->id;
        $integration->enabled = true;
        $integration->connected_at = now();
        $integration->config = [
            // Token bundle.
            'access_token'  => $tokens['access_token'],
            // refresh_token is only returned on FIRST consent. If a tenant
            // reconnects without revoking, this can come back empty — in
            // which case we keep the existing one if we had one.
            'refresh_token' => $tokens['refresh_token']
                ?? ($integration->config['refresh_token'] ?? null),
            'expires_at'    => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp,
            'scope'         => $tokens['scope'] ?? null,

            // Identity for UI display.
            'google_email'  => $email,
            'google_name'   => $name,

            // Calendar selection — preserve any existing choice (covers the
            // "reconnect to refresh tokens" flow). If null, the integration
            // page will render the picker step.
            'calendar_id'   => $integration->config['calendar_id'] ?? null,
            'calendar_name' => $integration->config['calendar_name'] ?? null,

            // Reset any prior error.
            'last_error'    => null,
        ];
        $integration->save();

        return redirect()
            ->route('tenant.integrations.show', 'google_calendar')
            ->with('status', __('Connected as :email — choose which calendar to sync to.', [
                'email' => $email ?? __('your Google account'),
            ]));
    }

    /**
     * Encode + sign a state payload with the app key. Format:
     *   base64url(json_payload).hex(hmac_sha256(payload, app_key))
     */
    protected function signState(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig  = hash_hmac('sha256', $body, config('app.key'));
        return $body.'.'.$sig;
    }

    /**
     * Verify + decode a state token. Returns the payload array on success,
     * null on any signature/expiry/format failure.
     */
    protected function verifyState(string $state): ?array
    {
        if (! str_contains($state, '.')) {
            return null;
        }

        [$body, $sig] = explode('.', $state, 2);
        $expected = hash_hmac('sha256', $body, config('app.key'));

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload) || empty($payload['expires_at'])) {
            return null;
        }

        if ((int) $payload['expires_at'] < now()->timestamp) {
            return null;
        }

        return $payload;
    }
}
