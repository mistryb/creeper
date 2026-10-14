<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The Model
    |--------------------------------------------------------------------------
    |
    | Keys are not configuration: every key lives on a user's keyring in the
    | app, and each company names the one it spends. The provider travels
    | with the key. Leave the model empty to take the provider's own default
    | rather than pinning a name that will age badly.
    |
    | `timeout` bounds each model call. It has to stay well inside the job's
    | own timeout, which has to stay inside `retry_after` in config/queue.php.
    |
    */

    'model' => env('DIGEST_MODEL'),
    'timeout' => (int) env('DIGEST_TIMEOUT', 180),

    /*
    |--------------------------------------------------------------------------
    | Web Searches
    |--------------------------------------------------------------------------
    |
    | Both discovery and the digest hand the model a web search tool. These
    | cap how many searches one run may make — the cost dial, since every
    | search is billed to the user's key alongside the tokens it returns.
    |
    */

    'max_searches' => (int) env('DIGEST_MAX_SEARCHES', 12),

    'discovery' => [
        'max_searches' => (int) env('DIGEST_DISCOVERY_MAX_SEARCHES', 8),
        'max_competitors' => (int) env('DIGEST_DISCOVERY_MAX_COMPETITORS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fetching
    |--------------------------------------------------------------------------
    |
    | Discovery reads the company's own homepage before it searches, so the
    | model knows what it is finding rivals for. The fetcher treats every URL
    | as hostile: the address is checked and pinned, redirects are followed
    | by hand, and the body is read under a byte ceiling. `max_characters`
    | caps how much of the page the model is asked to read.
    |
    */

    'fetch' => [
        'fetch_timeout' => (int) env('DIGEST_FETCH_TIMEOUT', 15),
        'max_characters' => (int) env('DIGEST_FETCH_MAX_CHARACTERS', 8000),
        'max_bytes' => (int) env('DIGEST_FETCH_MAX_BYTES', 2097152),
        'max_redirects' => (int) env('DIGEST_FETCH_MAX_REDIRECTS', 3),
        'user_agent' => env('DIGEST_FETCH_USER_AGENT', 'CreeperBot/1.0 (+https://github.com/creeper-app/creeper)'),
        'pin_address' => (bool) env('DIGEST_FETCH_PIN_ADDRESS', true),
    ],

];
