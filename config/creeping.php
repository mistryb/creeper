<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Creep Driver
    |--------------------------------------------------------------------------
    |
    | The driver that actually goes and does the creeping. "llm" reads the page
    | here, with a model, using the key the target was given in the app. "http"
    | hands the URL to your own creeping agent. "fake" returns plausible
    | product data without leaving the machine, which is how to look around
    | without a key.
    |
    */

    'driver' => env('CREEP_DRIVER', 'llm'),

    'drivers' => [

        'fake' => [
            //
        ],

        'http' => [
            'endpoint' => env('CREEP_AGENT_ENDPOINT'),
            'token' => env('CREEP_AGENT_TOKEN'),
            'timeout' => (int) env('CREEP_AGENT_TIMEOUT', 120),
        ],

        'llm' => [

            /*
             * Keys are not configuration. Every key lives on a user's keyring
             * in the app, and each target says which one it spends — there is
             * deliberately nothing to set here, so a key can never leak
             * through an environment file or an error page's config dump.
             *
             * The provider travels with the key. Leave the model empty to take
             * whatever the provider's own default is, rather than pinning a
             * name that will age badly.
             */
            'model' => env('CREEP_LLM_MODEL'),

            /*
             * The whole driver — fetching and inference together — has to
             * finish inside the budget, because a run that outlives its queue
             * reservation gets picked up and crept a second time. Keep the
             * budget comfortably under `retry_after` in config/queue.php.
             */
            'budget' => (int) env('CREEP_LLM_BUDGET', 70),
            'timeout' => (int) env('CREEP_LLM_TIMEOUT', 45),
            'fetch_timeout' => (int) env('CREEP_LLM_FETCH_TIMEOUT', 15),

            /*
             * How much page to accept. `max_characters` is the cost dial: it
             * caps what the model is asked to read, and so what each run
             * costs. The byte ceiling is a different question — it stops a
             * hostile or broken response filling the worker's memory.
             */
            'max_characters' => (int) env('CREEP_LLM_MAX_CHARACTERS', 12000),
            'max_bytes' => (int) env('CREEP_LLM_MAX_BYTES', 2097152),
            'max_redirects' => (int) env('CREEP_LLM_MAX_REDIRECTS', 3),

            'user_agent' => env('CREEP_LLM_USER_AGENT', 'CreeperBot/1.0 (+https://github.com/creeper-app/creeper)'),

            /*
             * Resolve each host once, check the address, then make curl connect
             * to that exact address. Without this, a hostname can pass the
             * public-address check and then resolve to something private a
             * moment later.
             */
            'pin_address' => (bool) env('CREEP_LLM_PIN_ADDRESS', true),

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
