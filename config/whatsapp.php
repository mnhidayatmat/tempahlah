<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    | 'baileys'     — talk to the Node sidecar (whatsapp-web via QR scan)
    | 'cloud_api'   — talk to Meta WhatsApp Cloud API (official, requires WABA)
    | 'deeplink'    — no auto-send. Generate wa.me deeplinks only.
    | 'null'        — log + drop. Useful for tests.
    */
    'driver' => env('WHATSAPP_DRIVER', 'baileys'),

    /*
    |--------------------------------------------------------------------------
    | Baileys sidecar (Node service on the same droplet, loopback)
    |--------------------------------------------------------------------------
    */
    'baileys' => [
        'base_url' => env('WHATSAPP_SIDECAR_URL', 'http://127.0.0.1:3001'),

        // Bearer token. Laravel <-> sidecar use this on every request.
        // Sidecar refuses connections without it.
        'auth_token' => env('WHATSAPP_SIDECAR_TOKEN'),

        // Inbound webhook signing — sidecar calls Laravel with this HMAC.
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),

        // HTTP timeout for status/QR polling. Send calls use a longer one.
        'timeout_seconds' => env('WHATSAPP_SIDECAR_TIMEOUT', 8),
        'send_timeout_seconds' => env('WHATSAPP_SIDECAR_SEND_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloud API (Phase B / v1.5 — kept here for parity)
    |--------------------------------------------------------------------------
    */
    'cloud_api' => [
        'phone_number_id'     => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'        => env('WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'graph_version'       => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound policy (anti-ban + PDPA hygiene)
    |--------------------------------------------------------------------------
    */
    'policy' => [
        // 'strict_guests' — outbound only to numbers that appear in the
        //                   tenant's own bookings.guests.phone column.
        // 'permissive'   — tenant decides. Disabled by default.
        'recipient_guard' => env('WHATSAPP_RECIPIENT_GUARD', 'strict_guests'),

        // Per-session safety net. Sidecar enforces a sliding gap; this is
        // an absolute daily ceiling enforced in Laravel before queueing.
        'daily_cap_per_session' => env('WHATSAPP_DAILY_CAP', 400),

        // Honour STOP/BERHENTI replies — guest user gets an opt-out flag
        // and future sends are blocked.
        'honour_opt_out' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Spaces session storage
    |--------------------------------------------------------------------------
    | Encrypted Baileys auth state is round-tripped to Spaces under this
    | prefix so the sidecar can restart cleanly without a re-scan.
    */
    'session_storage' => [
        'disk'   => env('WHATSAPP_SESSION_DISK', 'spaces'),
        'prefix' => env('WHATSAPP_SESSION_PREFIX', 'whatsapp-sessions'),
    ],

];
