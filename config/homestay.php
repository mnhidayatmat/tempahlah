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

    'channels' => [
        'toyyibpay' => [
            'base_url' => env('TOYYIBPAY_BASE_URL'),
            'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
            'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
        ],
        'billplz' => [
            'base_url' => env('BILLPLZ_BASE_URL'),
            'api_key' => env('BILLPLZ_API_KEY'),
            'collection_id' => env('BILLPLZ_COLLECTION_ID'),
            'signature' => env('BILLPLZ_SIGNATURE'),
        ],
    ],
];
