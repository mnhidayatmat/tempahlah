<?php

return [
    // Business/display timezone. The app stores absolute timestamps in UTC
    // (config/app.php), but Malaysian *calendar dates* — invoice/receipt dates,
    // document numbers, booking references — must be stamped and shown in MYT,
    // otherwise dates generated between 00:00–08:00 MYT land on the previous day.
    'timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Kuala_Lumpur'),

    'sst_rate' => env('SST_RATE', 0.08),
    'tourism_tax_per_night_foreigner' => env('TOURISM_TAX_PER_NIGHT_FOREIGNER', 10.00),
    // 0% on every tier — the 3-tier pricing model charges subscriptions, not
    // commissions. At 0 no Commission record is created (CreateBooking).
    'marketplace_commission_rate' => env('MARKETPLACE_COMMISSION_RATE', 0.0),
    'marketplace_trial_days' => env('MARKETPLACE_TRIAL_DAYS', 7),

    /*
    |---------------------------------------------------------------------------
    | Plans — the single source of truth for the 3-tier pricing model
    |---------------------------------------------------------------------------
    | free / pro / ultra. Read through App\Support\Billing\Plans, never directly:
    | `features` are ADDITIVE up the ladder (each plan lists only what it adds on
    | top of `inherits`; Plans::features() resolves the chain), and a `null`
    | limit means unlimited. Feature keys are the existing Pennant flag names —
    | FeatureServiceProvider defines one flag per key via Tenant::hasFeature().
    | Billing is monthly only: no yearly price exists on any plan.
    */
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price_monthly' => 0.0,
            'trial_days' => 0,
            'limits' => [
                'properties' => 1,
                'rooms_per_property' => 4,
                'bookings_per_month' => 20,
                'staff' => 1,
            ],
            'features' => [
                // Standard marketplace showcase — listing is open to every
                // host (PublishListing has never had a paywall); paid tiers
                // add ranking via marketplace_priority / marketplace_featured.
                'marketplace_listing',
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'price_monthly' => (float) env('PAID_TIER_PRICE', 49.00),
            'trial_days' => (int) env('PAID_TRIAL_DAYS', 30),
            'inherits' => 'free',
            'limits' => [
                'properties' => 3,
                'rooms_per_property' => null,
                'bookings_per_month' => null,
                'staff' => 3,
            ],
            'features' => [
                'multiple_properties',
                'payment_gateway',
                'invoice_documents',
                'auto_reminders',
                'whatsapp_business',
                'tenant_branded_emails',
                'brand_theme',
                'custom_invoice_template',
                'marketplace_priority',
                'dynamic_pricing',
                'reports',
                'export_reports',
                'api_access',
                'two_way_calendar_sync',
                'ical_channel_sync',
                'auto_operational_tasks',
                'inventory_alerts',
                'refund_handling',
                'ai_agent',
                'subdomain_booking_page',
            ],
        ],

        'ultra' => [
            'name' => 'Ultra',
            'price_monthly' => (float) env('ULTRA_TIER_PRICE', 89.00),
            'trial_days' => (int) env('PAID_TRIAL_DAYS', 30),
            'inherits' => 'pro',
            'limits' => [
                'properties' => null,
                'rooms_per_property' => null,
                'bookings_per_month' => null,
                'staff' => null,
            ],
            'features' => [
                'white_label',
                'advanced_reports',
                'marketplace_featured',
                'dedicated_support',
            ],
        ],
    ],

    // The per-tenant guest payment gateways a paid host can connect. Provider
    // precedence for billing lives in CreateGatewayBill::PRECEDENCE.
    'payment_gateways' => ['securepay', 'toyyibpay', 'billplz'],

    // Deprecated aliases of plans.pro — still read by the platform-billing
    // service; new code should use Plans::price() / Plans::trialDays().
    'paid_tier_price' => env('PAID_TIER_PRICE', 49.00),
    'paid_trial_days' => env('PAID_TRIAL_DAYS', 30),

    // New hosts are auto-enrolled in a card-free Pro trial at registration
    // (no Stripe/gateway step). When it lapses they fall straight back to Free.
    // A reminder email goes out `signup_trial_reminder_days` before it ends.
    'signup_trial_days' => env('SIGNUP_TRIAL_DAYS', 30),
    'signup_trial_reminder_days' => env('SIGNUP_TRIAL_REMINDER_DAYS', 3),

    // Days a lapsed paid subscription keeps its features while past_due, before
    // it is downgraded to free. Dunning happens inside this window.
    'subscription_grace_days' => env('SUBSCRIPTION_GRACE_DAYS', 7),

    /*
    |---------------------------------------------------------------------------
    | Affiliate / referral program
    |---------------------------------------------------------------------------
    | Affiliates share tempahlah.com/r/{code}; new host signups are attributed
    | via a cookie and the affiliate earns default_rate % of every subscription
    | payment for duration_months after the tenant's first paid conversion.
    | Commissions sit `pending` for hold_days (refund protection) before they
    | become payable. Per-affiliate rate/duration overrides live on the row.
    */
    'affiliate' => [
        'default_rate' => (float) env('AFFILIATE_DEFAULT_RATE', 20),
        'duration_months' => (int) env('AFFILIATE_DURATION_MONTHS', 12),
        'cookie_days' => (int) env('AFFILIATE_COOKIE_DAYS', 60),
        'hold_days' => (int) env('AFFILIATE_HOLD_DAYS', 30),
    ],

    /*
    |---------------------------------------------------------------------------
    | Platform subscription billing
    |---------------------------------------------------------------------------
    | Tempahlah's OWN Billplz merchant account, used to charge tenants the
    | RM 49/mo subscription. This is NOT the per-tenant gateway a host connects
    | to take guest booking payments — those credentials live encrypted in
    | `tenant_integrations` and never come from config.
    |
    | Leave the api_key blank and platform billing stays switched off: the
    | subscription page hides checkout, and `subscriptions:bill-cycle` no-ops.
    |
    | NOTE: Billplz has no recurring/subscription/mandate API — see
    | https://support.billplz.com/api. Each cycle is a one-off bill plus a
    | callback. True auto-charge needs their Tokenization product (paid plan,
    | request access from support@billplz.com; Visa/Mastercard only, and FPX
    | cannot be tokenized), which is layered on top of this later.
    */
    'platform_billing' => [
        'billplz' => [
            'api_key' => env('BILLPLZ_API_KEY'),
            'collection_id' => env('BILLPLZ_COLLECTION_ID'),
            'x_signature_key' => env('BILLPLZ_SIGNATURE'),
            'sandbox' => (bool) env('BILLPLZ_SANDBOX', true),
        ],

        // How many days before a trial/period ends we mint the next bill.
        'issue_lead_days' => env('SUBSCRIPTION_ISSUE_LEAD_DAYS', 3),

        // How long a minted bill stays payable before the cycle is re-billed.
        'invoice_due_days' => env('SUBSCRIPTION_INVOICE_DUE_DAYS', 14),

        // Master switch for Billplz card auto-renew (Tokenization). SUPERSEDED
        // by Stripe (below) for recurring — kept dormant. Even with credentials
        // set, card enrollment/checkout stays hidden and the daily command never
        // auto-charges until this is true.
        'tokenization' => (bool) env('BILLPLZ_TOKENIZATION', false),

        // Stripe — the recurring-billing rail. Stripe auto-charges each cycle
        // and runs its own dunning, so there is no daily charge command for
        // Stripe-managed subscriptions. Platform-owned keys (Tempahlah charges
        // its tenants), read from env per Stripe's own best-practices — never
        // the DB. With no secret_key + price_id the Stripe UI is hidden and the
        // webhook 409s, so the feature ships inert until these are set.
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            // The recurring MYR 49/mo Pro Price created in the Stripe Dashboard.
            'price_id' => env('STRIPE_PRICE_ID'),
            // The recurring MYR 89/mo Ultra Price. With this unset, Ultra
            // checkout is unavailable (the page shows Pro checkout only).
            'price_id_ultra' => env('STRIPE_PRICE_ID_ULTRA'),
        ],
    ],
];
