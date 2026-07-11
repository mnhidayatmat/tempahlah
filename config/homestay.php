<?php

return [
    'sst_rate' => env('SST_RATE', 0.08),
    'tourism_tax_per_night_foreigner' => env('TOURISM_TAX_PER_NIGHT_FOREIGNER', 10.00),
    'marketplace_commission_rate' => env('MARKETPLACE_COMMISSION_RATE', 0.03),
    'marketplace_trial_days' => env('MARKETPLACE_TRIAL_DAYS', 7),

    'free_tier_limits' => [
        'properties' => 1,
        'rooms_per_property' => 3,
        'bookings_per_month' => 20,
        'staff' => 1,
        'reports_history_days' => 30,
    ],

    'paid_tier_limits' => [
        'staff' => 5,
        'reports_history_days' => null,
    ],

    'paid_tier_price' => env('PAID_TIER_PRICE', 49.00),
    'paid_trial_days' => env('PAID_TRIAL_DAYS', 7),

    // Days a lapsed paid subscription keeps its features while past_due, before
    // it is downgraded to free. Dunning happens inside this window.
    'subscription_grace_days' => env('SUBSCRIPTION_GRACE_DAYS', 7),

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
            // The recurring MYR 49/mo Price created in the Stripe Dashboard.
            'price_id' => env('STRIPE_PRICE_ID'),
        ],
    ],
];
