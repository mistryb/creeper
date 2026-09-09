<?php

namespace App\Creeping\Drivers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Contracts\CreepInstructions;
use App\Creeping\Data\CreepResult;
use App\Creeping\Data\ProductPayload;
use App\Creeping\Exceptions\PageFetchFailed;
use App\Creeping\Fetching\PageDigest;
use App\Creeping\Fetching\PageFetcher;
use App\Enums\CreepProvider;
use App\Models\CreepRun;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Creeps a page by reading it, here, with a model.
 *
 * Three steps, all inside one run: fetch the page, boil it down to the parts
 * worth paying for, and ask a model what it says. No browser, so a shop that
 * assembles its price in JavaScript will come back thin — that is the trade
 * for a driver that needs nothing but an API key.
 *
 * Which model is asked what, and how much of the page it is shown, comes from
 * the target's {@see CreepInstructions}. This driver only knows about pages,
 * keys and clocks.
 *
 * The whole thing is bounded by a wall-clock budget, because a synchronous run
 * that outlives its queue reservation would be picked up and crept a second
 * time. See `config/creeping.php`.
 */
final class LlmCreepDriver implements CreepDriver
{
    /**
     * The least time worth starting a model call with.
     */
    private const FLOOR = 5;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $config,
        private PageFetcher $fetcher,
    ) {}

    public function name(): string
    {
        return 'llm';
    }

    public function creep(CreepRun $run): CreepResult
    {
        $started = microtime(true);
        $deadline = $started + (float) ($this->config['budget'] ?? 70);

        $instructions = $run->target->type->instructions();

        try {
            $page = $this->fetcher->fetch($run->target->url);
        } catch (PageFetchFailed $exception) {
            // A page that is gone, or an address we won't connect to, is not
            // going to be different in five minutes.
            if ($exception->definite) {
                return CreepResult::failed($exception->getMessage());
            }

            throw $exception;
        }

        $fetchMs = (int) round((microtime(true) - $started) * 1000);

        $digest = $instructions->digest($page->html, (int) ($this->config['max_characters'] ?? 12000));

        // Nothing readable came back. Saying so is cheaper and more useful
        // than paying a model to tell us the same thing.
        if ($digest->isThin()) {
            return CreepResult::failed($instructions->unreadable($page->url));
        }

        [$instance, $provider, $model] = $this->provider($run->target->user);

        $remaining = min(
            (int) ($this->config['timeout'] ?? 45),
            (int) floor($deadline - microtime(true)),
        );

        if ($remaining < self::FLOOR) {
            return CreepResult::failed('Fetching the page used up the whole run, so the model never saw it.');
        }

        $prompt = $digest->toPrompt($page->url);

        try {
            $response = $instructions->agent()->prompt(
                $prompt,
                provider: $instance,
                model: $model,
                timeout: $remaining,
            );
        } catch (InsufficientCreditsException $exception) {
            return CreepResult::failed(
                'The model provider says there is no credit left on this key. Retrying will not help until it is topped up.'
            );
        } catch (RequestException $exception) {
            return $this->rejected($exception, $provider);
        } finally {
            // The key lives in the config repository for exactly as long as
            // the call takes, and no longer.
            $this->forget($instance);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException(sprintf(
                'The %s agent did not return structured output.',
                $run->target->type->value,
            ));
        }

        return CreepResult::succeeded($this->payload(
            $response, $page->url, $provider, $model, $digest, $fetchMs, $started, $prompt, $page->redirects,
        ));
    }

    /**
     * Register the provider this run should bill, and say which model to use.
     *
     * The user's own key is preferred — they pay their provider directly — and
     * the configured key is the fallback a self-hosted install runs on.
     *
     * @return array{0: string, 1: CreepProvider, 2: string|null}
     */
    private function provider(User $user): array
    {
        $model = $this->config['model'] ?? null;
        $model = is_string($model) && $model !== '' ? $model : null;

        if ($user->hasCreepApiKey() && $user->creep_api_provider instanceof CreepProvider) {
            return [
                $this->register("creep_user_{$user->id}", $user->creep_api_provider, (string) $user->creep_api_key),
                $user->creep_api_provider,
                $model,
            ];
        }

        $key = $this->config['key'] ?? null;

        if (! is_string($key) || $key === '') {
            // A misconfigured install is not a dead target. Throwing keeps
            // this out of the target's failure streak, the same way a missing
            // agent endpoint does for the http driver.
            throw new RuntimeException(
                'No model API key is configured for the llm driver. Set CREEP_LLM_API_KEY, or add a key in settings.'
            );
        }

        $provider = CreepProvider::tryFrom((string) ($this->config['provider'] ?? '')) ?? CreepProvider::Anthropic;

        return [$this->register('creep_app', $provider, $key), $provider, $model];
    }

    /**
     * Make a one-off AI SDK provider that spends this particular key.
     *
     * The SDK takes its credentials from configuration, so a key that belongs
     * to a user has to be put there for the duration of the call. Everything
     * about that — the naming, the teardown — is deliberately confined to this
     * method and {@see forget()}.
     */
    private function register(string $instance, CreepProvider $provider, #[\SensitiveParameter] string $key): string
    {
        config(["ai.providers.{$instance}" => [
            'driver' => $provider->lab()->value,
            'key' => $key,
        ]]);

        // Resolved providers are memoised by name, so a stale one would keep
        // spending the previous key.
        Ai::forgetInstance($instance);

        return $instance;
    }

    /**
     * Put the key beyond reach again.
     *
     * `config()` is dumped by error pages and reporting tools, so leaving a
     * key in it after the call would be a slow leak.
     */
    private function forget(string $instance): void
    {
        Ai::forgetInstance($instance);

        config(["ai.providers.{$instance}" => null]);
    }

    /**
     * Decide what a rejected request means.
     *
     * The SDK maps the retryable statuses to its own exceptions before we see
     * them, so anything arriving here is a plain HTTP failure — and the only
     * ones worth retrying are the ones that aren't about us.
     */
    private function rejected(RequestException $exception, CreepProvider $provider): CreepResult
    {
        $status = $exception->response->status();

        if ($status === 401 || $status === 403) {
            return CreepResult::failed(
                "{$provider->label()} rejected the API key on file. Check it in settings — retrying the same key will not help."
            );
        }

        if ($status === 400 || $status === 404) {
            return CreepResult::failed(
                "{$provider->label()} refused the request: ".mb_substr($exception->response->body(), 0, 300)
            );
        }

        throw $exception;
    }

    /**
     * What the run hands back.
     *
     * The product fields are whatever the model reported, loose, for
     * {@see ProductPayload} to normalise. Everything after
     * them is diagnostic: unknown to that class, so it is kept verbatim on the
     * snapshot's `extra`, which is where the cost of a run and the shape of
     * the page it came from belong.
     *
     * Note what is absent: the API key, and the page's HTML.
     *
     * @return array<string, mixed>
     */
    private function payload(
        StructuredAgentResponse $response,
        string $url,
        CreepProvider $provider,
        ?string $model,
        PageDigest $digest,
        int $fetchMs,
        float $started,
        string $prompt,
        int $redirects,
    ): array {
        $fields = array_filter(
            $response->toArray(),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return [
            ...$fields,
            'source_url' => $url,
            'provider' => $provider->value,
            ...($model === null ? [] : ['model' => $model]),
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
            ],
            'fetch_ms' => $fetchMs,
            'total_ms' => (int) round((microtime(true) - $started) * 1000),
            'prompt_characters' => mb_strlen($prompt),
            'redirects' => $redirects,
            'digest' => $digest->fingerprint(),
        ];
    }
}
