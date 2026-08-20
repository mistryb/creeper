<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Enabled
    |--------------------------------------------------------------------------
    |
    | Creeper is a paid SaaS and an app you can self-host for free. When this
    | is false there are no plans, no limits, and no billing UI at all — the
    | default, so a fresh clone is a complete application out of the box.
    |
    */

    'enabled' => (bool) env('BILLING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | "targets" caps how many creep targets a user may keep. "min_frequency"
    | is the fastest schedule the plan allows — anything more demanding is
    | rejected at validation time.
    |
    */

    'default_plan' => 'free',

    'plans' => [

        'free' => [
            'name' => 'Free',
            'price' => 'Free',
            'targets' => 3,
            'min_frequency' => 'daily',
            'stripe_price' => null,
        ],

        'pro' => [
            'name' => 'Pro',
            'price' => '$12/month',
            'targets' => 50,
            'min_frequency' => 'hourly',
            'stripe_price' => env('STRIPE_PRICE_PRO'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Name
    |--------------------------------------------------------------------------
    |
    | The Cashier subscription name used for the single subscription a user
    | may hold. Single-user accounts, one subscription each.
    |
    */

    'subscription' => 'default',

];
