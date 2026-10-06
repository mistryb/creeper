<?php

namespace App\Creeping\Drivers;

use App\Ai\UserKeyProvider;
use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Data\CreepResult;
use App\Creeping\Data\PagePayload;
use App\Creeping\Exceptions\PageFetchFailed;
use App\Creeping\Fetching\PageDigest;
use App\Creeping\Fetching\PageFetcher;
use App\Creeping\WatchInstructions;
use App\Enums\CreepProvider;
use App\Models\CreepRun;
use Illuminate\Http\Client\RequestException;
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
 * What the model is shown and asked comes from {@see WatchInstructions}, which
 * puts the user's own description of what to watch in the prompt. This driver
 * only knows about pages, keys and clocks.
 *
 * The key is always one the user added in the app and picked for this page,
 * spent through {@see UserKeyProvider}. There is no key in the environment to
 * fall back on, so a page without one cannot be crept until somebody
 * chooses a key for it.
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
        private UserKeyProvider $keys = new UserKeyProvider,
        private WatchInstructions $instructions = new WatchInstructions,
    ) {}

    public function name(): string
    {
        return 'llm';
    }

    public function creep(CreepRun $run): CreepResult
    {
        $started = microtime(true);
        $deadline = $started + (float) ($this->config['budget'] ?? 70);

        $instructions = $this->instructions;

        $apiKey = $run->watchedPage->apiKey;

        // Checked before the page is fetched: without a key there is nothing
        // to read the page with, so fetching it would only waste the request.
        if ($apiKey === null) {
            return CreepResult::failed(
                'This page has no API key. Choose one in the page\'s settings, or add one under Settings → API keys.'
            );
        }

        try {
            $page = $this->fetcher->fetch($run->watchedPage->url);
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

        $provider = $apiKey->provider;
        $model = $this->model();

        $remaining = min(
            (int) ($this->config['timeout'] ?? 45),
            (int) floor($deadline - microtime(true)),
        );

        if ($remaining < self::FLOOR) {
            return CreepResult::failed('Fetching the page used up the whole run, so the model never saw it.');
        }

        $prompt = $instructions->prompt($run->watchedPage, $digest, $page->url);

        try {
            $response = $this->keys->using($apiKey, fn (string $instance) => $instructions->agent()->prompt(
                $prompt,
                provider: $instance,
                model: $model,
                timeout: $remaining,
            ));
        } catch (InsufficientCreditsException $exception) {
            return CreepResult::failed(
                'The model provider says there is no credit left on this key. Retrying will not help until it is topped up.'
            );
        } catch (RequestException $exception) {
            return $this->rejected($exception, $provider);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The watched page agent did not return structured output.');
        }

        return CreepResult::succeeded($this->payload(
            $response, $page->url, $provider, $model, $digest, $fetchMs, $started, $prompt, $page->redirects,
        ));
    }

    /**
     * The model to ask. Empty means the provider's own default, rather than a
     * pinned name that will age badly.
     */
    private function model(): ?string
    {
        $model = $this->config['model'] ?? null;

        return is_string($model) && $model !== '' ? $model : null;
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
                "{$provider->label()} rejected the key this page uses. Check it under Settings → API keys — retrying the same key will not help."
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
     * The summary and facts are whatever the model reported, loose, for
     * {@see PagePayload} to normalise. Everything after
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
