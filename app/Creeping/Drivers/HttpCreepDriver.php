<?php

namespace App\Creeping\Drivers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Data\CreepResult;
use App\Models\CreepRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Hands the URL to a creeping agent over HTTP.
 *
 * The agent may answer either way:
 *
 *  - 200 with a JSON product body — the run completes immediately.
 *  - 202 Accepted — the agent took the work and will POST its results to the
 *    signed `callback_url` when it's done. Real agents take minutes, so this
 *    is the path most of them will want.
 *
 * The request carries the user's own model API key when they have one on
 * file, so inference is billed to them by their provider rather than to us.
 */
class HttpCreepDriver implements CreepDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function name(): string
    {
        return 'http';
    }

    public function creep(CreepRun $run): CreepResult
    {
        $endpoint = $this->config['endpoint'] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('No creeping agent endpoint is configured. Set CREEP_AGENT_ENDPOINT.');
        }

        $token = $this->config['token'] ?? null;
        $timeout = (int) ($this->config['timeout'] ?? 120);

        $request = Http::asJson()
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout(10)
            // `throw: false` keeps error statuses as responses rather than
            // exceptions, so only connection-level blips are retried here and
            // every HTTP status is decided deliberately below.
            ->retry([250, 1000], throw: false);

        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($endpoint, [
            'run_id' => $run->id,
            'target_id' => $run->target->id,
            'type' => $run->target->type->value,
            'url' => $run->target->url,
            'settings' => $run->target->settings ?? [],
            'callback_url' => $this->callbackUrl($run),
            // The key this target was given. The agent spends it on the
            // user's behalf; we never hold a balance and never mark it up.
            ...array_filter(['api_key' => $run->target->apiKey?->key]),
        ]);

        if ($response->accepted()) {
            return CreepResult::pending();
        }

        if ($response->successful()) {
            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];

            return CreepResult::succeeded($body);
        }

        // The agent understood us and said no — a bad URL, an unsupported
        // site. Retrying won't change the answer, so end the run here.
        if ($response->clientError()) {
            return CreepResult::failed(
                'The creeping agent rejected the target: '.$response->status().' '.mb_substr($response->body(), 0, 500)
            );
        }

        // Server errors are the agent's problem, and probably temporary.
        $response->throw();

        return CreepResult::failed('The creeping agent returned an unexpected response.');
    }

    /**
     * A signed, expiring URL the agent posts results back to.
     */
    protected function callbackUrl(CreepRun $run): string
    {
        return URL::temporarySignedRoute(
            'creep.callback',
            Carbon::now()->addMinutes((int) config('creeping.callback_ttl', 180)),
            ['run' => $run->id],
        );
    }
}
