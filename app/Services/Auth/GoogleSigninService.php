<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

/**
 * Sign-in-with-Google OAuth client. Same Google Cloud project as the
 * Calendar integration (reuses the client_id + client_secret), but with
 *
 *   - the `openid email profile` scopes (instead of calendar scopes)
 *   - a different redirect URI (/auth/google/callback) so the auth flow
 *     doesn't collide with the calendar one
 *
 * Both redirect URIs must be registered on the OAuth client in the
 * Google Cloud Console:
 *   https://tempahlah.com/oauth/google/callback  (calendar)
 *   https://tempahlah.com/auth/google/callback   (sign-in)
 */
class GoogleSigninService
{
    protected const AUTH_BASE = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    /**
     * Build Google's consent URL. State is HMAC-signed so the callback
     * can verify it wasn't forged or replayed.
     *
     * No `access_type=offline` / `prompt=consent` here — we only need
     * the email + name once at sign-in, no refresh token required.
     */
    public function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => config('services.google_signin.client_id'),
            'redirect_uri'  => config('services.google_signin.redirect_uri'),
            'response_type' => 'code',
            'scope'         => implode(' ', config('services.google_signin.scopes')),
            'state'         => $state,
            'include_granted_scopes' => 'true',
        ]);

        return self::AUTH_BASE.'?'.$params;
    }

    public function exchangeCodeForTokens(string $code): array
    {
        return Http::asForm()
            ->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => config('services.google_signin.client_id'),
                'client_secret' => config('services.google_signin.client_secret'),
                'redirect_uri'  => config('services.google_signin.redirect_uri'),
                'grant_type'    => 'authorization_code',
            ])
            ->throw()
            ->json();
    }

    /**
     * Fetch the authenticated user's profile via Google's UserInfo endpoint.
     * Returns the canonical claims: `sub` (stable Google ID), `email`,
     * `email_verified`, `name`, `given_name`, `family_name`, `picture`.
     */
    public function fetchUserInfo(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::USERINFO_URL)
            ->throw()
            ->json();
    }
}
