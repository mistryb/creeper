<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Creep Driver
    |--------------------------------------------------------------------------
    |
    | The driver that actually goes and does the creeping. "fake" returns
    | plausible product data without leaving the machine and is what a fresh
    | clone runs on. "http" hands the URL to your creeping agent.
    |
    */

    'driver' => env('CREEP_DRIVER', 'fake'),

    'drivers' => [

        'fake' => [
            //
        ],

        'http' => [
            'endpoint' => env('CREEP_AGENT_ENDPOINT'),
            'token' => env('CREEP_AGENT_TOKEN'),
            'timeout' => (int) env('CREEP_AGENT_TIMEOUT', 120),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Callback Lifetime
    |--------------------------------------------------------------------------
    |
    | How many minutes an agent has to post its results back to the signed
    | callback URL before the signature expires. Long-running agents need
    | plenty of room here.
    |
    */

    'callback_ttl' => (int) env('CREEP_CALLBACK_TTL', 180),

    /*
    |--------------------------------------------------------------------------
    | Run Retries
    |--------------------------------------------------------------------------
    |
    | How many times a single creep is retried before the run is marked
    | failed, and how long to wait between attempts (in seconds).
    |
    */

    'retries' => (int) env('CREEP_RETRIES', 3),

    'backoff' => [60, 300, 900],

];
