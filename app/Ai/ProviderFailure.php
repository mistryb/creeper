<?php

namespace App\Ai;

use App\Enums\CreepProvider;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Says, in a sentence somebody can act on, why a model provider turned a call
 * down — for runs that are started by a person watching the screen and are
 * never retried behind their back.
 */
final class ProviderFailure
{
    /**
     * The sentence for a provider failure, or null when the exception is not
     * one and should be left to propagate.
     */
    public static function explain(Throwable $exception, CreepProvider $provider): ?string
    {
        return match (true) {
            $exception instanceof InsufficientCreditsException => 'The model provider says there is no credit left on this key. Top it up, or run again with another key.',
            $exception instanceof RateLimitedException, $exception instanceof ProviderOverloadedException => "{$provider->label()} is busy or rate limiting this key. Try again in a minute.",
            $exception instanceof RequestException => match ($exception->response->status()) {
                401, 403 => "{$provider->label()} rejected this API key. Check it under Settings → API keys.",
                default => "{$provider->label()} refused the request: ".mb_substr($exception->response->body(), 0, 300),
            },
            default => null,
        };
    }
}
