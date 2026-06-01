<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Calendar (platform-owned OAuth app)
    |--------------------------------------------------------------------------
    | One OAuth client registered by Tempahlah at console.cloud.google.com.
    | Tenants click "Connect Google Calendar" and grant access to their own
    | calendar — they never see these credentials.
    */
    'google_calendar' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_CALENDAR_REDIRECT_URI', env('APP_URL').'/oauth/google/callback'),
        // Narrower than full /auth/calendar — avoids "restricted scope"
        // verification friction (events scope is "sensitive" but not "restricted").
        'scopes' => [
            'https://www.googleapis.com/auth/calendar.events',
            'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
            'openid',
            'email',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | "Sign in with Google" — same OAuth project as Calendar
    |--------------------------------------------------------------------------
    | Reuses the GOOGLE_CALENDAR_CLIENT_ID/SECRET by default (one OAuth
    | client per Google Cloud project is the simplest setup) but uses a
    | separate redirect URI so the sign-in flow doesn't collide with the
    | calendar one. Override the GOOGLE_SIGNIN_* vars in .env if you want
    | a dedicated OAuth client for sign-in instead.
    |
    | IMPORTANT: register the sign-in redirect URI in the Google Cloud
    | Console:  https://tempahlah.com/auth/google/callback
    */
    'google_signin' => [
        'client_id'     => env('GOOGLE_SIGNIN_CLIENT_ID', env('GOOGLE_CALENDAR_CLIENT_ID')),
        'client_secret' => env('GOOGLE_SIGNIN_CLIENT_SECRET', env('GOOGLE_CALENDAR_CLIENT_SECRET')),
        'redirect_uri'  => env('GOOGLE_SIGNIN_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
        // OpenID-Connect basics — just enough to get email + name back.
        'scopes' => [
            'openid',
            'email',
            'profile',
        ],
    ],

];
