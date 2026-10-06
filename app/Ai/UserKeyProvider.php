<?php

namespace App\Ai;

use App\Models\ApiKey;
use Closure;
use Laravel\Ai\Ai;

/**
 * Spends one of a user's own API keys for the length of a single call.
 *
 * laravel/ai has no `->withApiKey()`: it takes credentials from
 * configuration. So the key is registered as a named provider just before the
 * call and torn down straight after, in a `finally` — `config()` is dumped by
 * error pages, and a key left in it would be a leak.
 *
 * This is an undocumented seam in a pre-1.0 package, which is why every model
 * call that bills a user goes through here and nowhere else.
 */
final class UserKeyProvider
{
    /**
     * Run a call against a provider that spends this key.
     *
     * The callback receives the provider instance name, to pass straight on as
     * `prompt(provider: ...)`.
     *
     * @template TResult
     *
     * @param  Closure(string): TResult  $call
     * @return TResult
     */
    public function using(ApiKey $apiKey, Closure $call): mixed
    {
        // Named per key, so two keys can never share a memoised provider.
        $instance = "creep_key_{$apiKey->id}";

        config(["ai.providers.{$instance}" => [
            'driver' => $apiKey->provider->lab()->value,
            'key' => $apiKey->key,
        ]]);

        // Resolved providers are memoised by name, so a stale one would keep
        // spending the previous key.
        Ai::forgetInstance($instance);

        try {
            return $call($instance);
        } finally {
            Ai::forgetInstance($instance);

            // The config repository has no forget(); null is as gone as it gets.
            config(["ai.providers.{$instance}" => null]);
        }
    }
}
