<?php

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\CreepManager;
use App\Enums\CreepOutcome;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'creeping.driver' => 'http',
        'creeping.drivers.http.endpoint' => 'https://agent.test/creep',
        'creeping.drivers.http.token' => 'secret-token',
    ]);
});

function httpDriver(): CreepDriver
{
    return app(CreepManager::class)->driver('http');
}

it('sends the target and a signed callback URL to the agent', function () {
    Http::fake(['agent.test/*' => Http::response(['title' => 'Kettle', 'price' => 24.99])]);

    $run = CreepRun::factory()->running()->create();

    httpDriver()->creep($run);

    Http::assertSent(function (Request $request) use ($run): bool {
        expect($request->header('Authorization'))->toBe(['Bearer secret-token']);

        return $request->url() === 'https://agent.test/creep'
            && $request['run_id'] === $run->id
            && $request['url'] === $run->target->url
            && $request['type'] === 'product'
            && str_contains((string) $request['callback_url'], '/webhooks/creep/'.$run->id)
            && str_contains((string) $request['callback_url'], 'signature=');
    });
});

it('completes the run when the agent answers straight away', function () {
    Http::fake(['agent.test/*' => Http::response([
        'title' => 'Stainless Kettle',
        'price' => '£24.99',
        'availability' => 'in_stock',
    ])]);

    $target = CreepTarget::factory()->create();

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('http')
        ->and($target->snapshots()->sole()->price_amount)->toBe(2499);
});

it('leaves the run open when the agent accepts the work for later', function () {
    Http::fake(['agent.test/*' => Http::response(null, 202)]);

    $target = CreepTarget::factory()->due()->create();

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();
    $target->refresh();

    expect($run->status)->toBe(RunStatus::Running)
        ->and($target->snapshots()->count())->toBe(0)
        // The schedule still moves on, so the sweeper doesn't pile up
        // duplicate work while the agent is thinking.
        ->and($target->next_creep_at?->isFuture())->toBeTrue();
});

it('gives up on a target the agent rejects', function () {
    Http::fake(['agent.test/*' => Http::response(['error' => 'Not a product page'], 422)]);

    $run = CreepRun::factory()->running()->create();

    $result = httpDriver()->creep($run);

    expect($result->outcome)->toBe(CreepOutcome::Failed)
        ->and($result->error)->toContain('Not a product page');
});

it('throws when the agent itself is broken, so the queue retries', function () {
    Http::fake(['agent.test/*' => Http::response('', 503)]);

    httpDriver()->creep(CreepRun::factory()->running()->create());
})->throws(RequestException::class);

it('records the attempt as failed before letting the exception through', function () {
    Http::fake(['agent.test/*' => Http::response('', 503)]);

    $target = CreepTarget::factory()->create();

    try {
        RunCreep::dispatchSync($target);
    } catch (RequestException) {
        // The queue would retry; here we only care what was written down.
    }

    $run = $target->runs()->sole();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->not->toBeNull();
});

it('refuses to run without an agent endpoint', function () {
    config(['creeping.drivers.http.endpoint' => null]);

    httpDriver()->creep(CreepRun::factory()->running()->create());
})->throws(RuntimeException::class, 'No creeping agent endpoint is configured. Set CREEP_AGENT_ENDPOINT.');
