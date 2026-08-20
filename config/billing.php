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
    | There is one paid plan, on purpose. "targets" caps how many creep targets
    | a user may keep (null for no cap) and "min_frequency" is the fastest
    | schedule allowed. "included_runs" is the allowance the flat fee covers;
    | runs beyond it are billed through the meter below.
    |
    | The allowance is enforced by Stripe, not here — the metered price is
    | tiered so the first "included_runs" units cost nothing. This app reports
    | every billable run and lets Stripe do the arithmetic, so a user's
    | allowance always tracks their real billing period.
    |
    */

    'default_plan' => 'none',

    'plans' => [

        'none' => [
            'name' => 'No subscription',
            'price' => null,
            'amount' => null,
            'targets' => 0,
            'min_frequency' => 'daily',
            'included_runs' => 0,
            'stripe_price' => null,
            'metered_price' => null,
        ],

        'creeper' => [
            'name' => 'Creeper',
            'price' => '$7/month',
            // Minor units. Never floats for money.
            'amount' => 700,
            'currency' => 'USD',
            'targets' => null,
            'min_frequency' => 'hourly',
            'included_runs' => 1500,
            'stripe_price' => env('STRIPE_PRICE_CREEPER'),
            'metered_price' => env('STRIPE_PRICE_CREEP_RUN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Run Meter
    |--------------------------------------------------------------------------
    |
    | "event" must match the event name of the Stripe meter exactly, or usage
    | is reported into the void. "unit_amount" is what a run past the
    | allowance costs, in minor units, and exists so the marketing page and
    | the billing screen can quote a number without hard-coding one.
    |
    */

    'meter' => [
        'event' => env('STRIPE_METER_EVENT', 'creep_run'),
        'unit_amount' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Run Ceiling
    |--------------------------------------------------------------------------
    |
    | A hard stop on billable runs per calendar month, so a runaway schedule
    | cannot quietly bill somebody hundreds of dollars. Runs are refused with
    | a visible error once this is hit. Set to null to remove the stop.
    |
    */

    'run_ceiling' => (int) env('BILLING_RUN_CEILING', 25000),

    /*
    |--------------------------------------------------------------------------
    | Bring Your Own Key
    |--------------------------------------------------------------------------
    |
    | The model API key belongs to the user — they pay their provider directly
    | and we never mark it up. When this is on, a target belonging to a user
    | with no key on file will not be crept.
    |
    */

    'requires_api_key' => (bool) env('BILLING_REQUIRES_API_KEY', true),

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
