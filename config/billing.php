<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | Whether this install charges for itself. It does only when Stripe is
    | fully configured — a secret key and a price for each plan. Leave any of
    | them out and Creeper runs self-hosted: no plans, no trial, no limit on
    | companies, and nothing on the screen that mentions money.
    |
    */

    'enabled' => filled(env('STRIPE_SECRET'))
        && filled(env('STRIPE_PRICE_SINGLE'))
        && filled(env('STRIPE_PRICE_UNLIMITED')),

    /*
    |--------------------------------------------------------------------------
    | Prices
    |--------------------------------------------------------------------------
    |
    | The Stripe price each plan subscribes to. See App\Enums\Plan for what
    | each plan allows; the amounts shown in the app live there too, and must
    | match what these prices charge.
    |
    */

    'prices' => [
        'single' => env('STRIPE_PRICE_SINGLE'),
        'unlimited' => env('STRIPE_PRICE_UNLIMITED'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    |
    | Days a new account may use one company before it has to pick a plan.
    | No card is taken up front.
    |
    */

    'trial_days' => 7,

];
