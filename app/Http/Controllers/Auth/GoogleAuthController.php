<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Auth\GoogleSigninService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Continue with Google" — sign-in + sign-up via Google OAuth on the
 * /login and /register pages.
 *
 *   GET /auth/google           start()    — redirects to Google's consent
 *   GET /auth/google/callback  callback() — handles the code, logs in or
 *                                           auto-creates a tenant.
 *
 * Both endpoints sit in the `guest` middleware group; the callback
 * explicitly logs the user in once the user row exists. New users get
 * a tenant auto-created from their Google name (the slug is derived from
 * the name + uniqued); hosts can rename via Settings later.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private GoogleSigninService $google) {}

    /**
     * Build the consent URL and bounce. HMAC-signed state carries the
     * session ID + intent (login / register) so we can:
     *   - reject forged callbacks (CSRF)
     *   - reject replays (10-minute expiry)
     *   - reject session swaps (tab hijack)
     *   - know whether to flash "Welcome back" vs "Welcome aboard"
     */
    public function start(Request $request): RedirectResponse
    {
        $intent = $request->query('intent') === 'register' ? 'register' : 'login';

        if (! config('services.google_signin.client_id')) {
            // Misconfigured server — fail clearly instead of bouncing the
            // user to a broken Google page.
            return back()->with('status', __('Sign in with Google is not available right now.'));
        }

        $state = $this->signState([
            'session_id' => $request->session()->getId(),
            'intent'     => $intent,
            'nonce'      => Str::random(16),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->away($this->google->authorizeUrl($state));
    }

    /**
     * Handle Google's redirect back to us. Possible inbound shapes:
     *   ?code=…&state=…              → success path
     *   ?error=access_denied&state=… → user cancelled consent
     *   missing code/error            → malformed, treat as error
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('login')
                ->with('status', __('Google sign-in cancelled.'));
        }

        $code  = $request->query('code');
        $state = $request->query('state');

        if (! $code || ! $state) {
            return redirect()->route('login')
                ->with('status', __('Google sign-in failed: missing code.'));
        }

        $payload = $this->verifyState($state);
        if (! $payload) {
            Log::warning('Google sign-in: invalid state', [
                'state' => substr($state, 0, 12).'…',
            ]);
            return redirect()->route('login')
                ->with('status', __('Google sign-in failed: invalid state. Please try again.'));
        }
        if ($payload['session_id'] !== $request->session()->getId()) {
            Log::warning('Google sign-in: session mismatch');
            return redirect()->route('login')
                ->with('status', __('Google sign-in failed: session changed. Please try again.'));
        }

        // Exchange code → access_token, then fetch profile.
        try {
            $tokens   = $this->google->exchangeCodeForTokens($code);
            $userInfo = $this->google->fetchUserInfo($tokens['access_token'] ?? '');
        } catch (\Throwable $e) {
            Log::warning('Google sign-in: token/userinfo exchange failed', [
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('login')
                ->with('status', __('Google sign-in failed during handshake.'));
        }

        $email = $userInfo['email'] ?? null;
        $name  = $userInfo['name']  ?? null;
        $emailVerified = ! empty($userInfo['email_verified']);

        if (! $email || ! $emailVerified) {
            // Google didn't confirm the email — refuse rather than create
            // an account on an unverified address.
            return redirect()->route('login')
                ->with('status', __('Google sign-in failed: email not verified by Google.'));
        }

        // Path A — existing user → straight log-in.
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return $this->loginAndRedirect($request, $user, returning: true);
        }

        // Path B — new user. Create the account only; we do NOT auto-generate
        // a tenant/business name from the Google profile or email. Log them in
        // and send them to the one-step "name your homestay" onboarding, where
        // the host types the name guests will see (mirrors the /register form).
        $user = User::create([
            'name'      => $name ?: explode('@', $email)[0],
            'email'     => $email,
            'phone'     => null,
            // Google users have no password — set a random unguessable hash so
            // the NOT NULL constraint is satisfied. They authenticate via Google
            // going forward (they can use "Forgot password" to set one too).
            'password'          => Hash::make(Str::random(40)),
            'locale'            => app()->getLocale(),
            'user_type'         => User::TYPE_TENANT_USER,
            'email_verified_at' => now(),
        ]);

        Auth::login($user, remember: true);

        return redirect()->route('onboarding.homestay')
            ->with('status', __('One last step — name your homestay.'));
    }

    /**
     * Common post-callback path: log the user in, pin their tenant in the
     * session, and route them to the dashboard with a context-appropriate
     * flash. Splitting login vs register lets us tailor the welcome copy.
     */
    protected function loginAndRedirect(Request $request, User $user, bool $returning): RedirectResponse
    {
        Auth::login($user, remember: true);

        // Pin the first active tenant they own/belong to so dashboard
        // middleware (SetTenantContext / RequireTenant) doesn't bounce
        // them to /onboard on the next request.
        $membership = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->with('tenant:id,public_id,status')
            ->first();

        if ($membership?->tenant && $membership->tenant->status === 'active') {
            $request->session()->put('current_tenant_public_id', $membership->tenant->public_id);
        }

        $flash = $returning
            ? __('Welcome back, :name!', ['name' => Str::before($user->name ?? $user->email, ' ')])
            : __('Welcome, :name! Your homestay account is ready — rename it in Settings any time.', [
                'name' => Str::before($user->name ?? $user->email, ' '),
            ]);

        return redirect()->intended(route('tenant.dashboard'))->with('status', $flash);
    }

    // ── HMAC-signed state, same pattern as GoogleCalendarOAuthController ──

    protected function signState(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig  = hash_hmac('sha256', $body, config('app.key'));
        return $body.'.'.$sig;
    }

    protected function verifyState(string $state): ?array
    {
        if (! str_contains($state, '.')) return null;

        [$body, $sig] = explode('.', $state, 2);
        $expected = hash_hmac('sha256', $body, config('app.key'));
        if (! hash_equals($expected, $sig)) return null;

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) return null;

        $payload = json_decode($json, true);
        if (! is_array($payload) || empty($payload['expires_at'])) return null;
        if ((int) $payload['expires_at'] < now()->timestamp) return null;

        return $payload;
    }
}
