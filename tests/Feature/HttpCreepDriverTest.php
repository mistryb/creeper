<?php

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\CreepManager;
use App\Enums\CreepOutcome;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\WatchedPage;
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

it('sends the page and a signed callback URL to the agent', function () {
    Http::fake(['agent.test/*' => Http::response(['summary' => 'Pro is $20.', 'facts' => [['label' => 'Pro plan', 'value' => '$20']]])]);

    $run = CreepRun::factory()->running()->create();

    httpDriver()->creep($run);

    Http::assertSent(function (Request $request) use ($run): bool {
        expect($request->header('Authorization'))->toBe(['Bearer secret-token']);

        return $request->url() === 'https://agent.test/creep'
            && $request['run_id'] === $run->id
            && $request['watched_page_id'] === $run->watchedPage->id
            // Kept under its old name for agents written before watched pages.
            && $request['target_id'] === $run->watchedPage->id
            && $request['url'] === $run->watchedPage->url
            && $request['watch_for'] === $run->watchedPage->watch_for
            && str_contains((string) $request['callback_url'], '/webhooks/creep/'.$run->id)
            && str_contains((string) $request['callback_url'], 'signature=');
    });
});

it('completes the run when the agent answers straight away', function () {
    Http::fake(['agent.test/*' => Http::response([
        'summary' => 'Pro is $20 a month.',
        'facts' => ['Pro plan' => '$20/month'],
    ])]);

    $watchedPage = WatchedPage::factory()->create();

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('http')
        // A plain label => value map is accepted as readily as a list.
        ->and($watchedPage->snapshots()->sole()->facts)->toBe([['label' => 'Pro plan', 'value' => '$20/month']]);
});

it('leaves the run open when the agent accepts the work for later', function () {
    Http::fake(['agent.test/*' => Http::response(null, 202)]);

    $watchedPage = WatchedPage::factory()->due()->create();

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();
    $watchedPage->refresh();

    expect($run->status)->toBe(RunStatus::Running)
        ->and($watchedPage->snapshots()->count())->toBe(0)
        // The schedule still moves on, so the sweeper doesn't pile up
        // duplicate work while the agent is thinking.
        ->and($watchedPage->next_creep_at?->isFuture())->toBeTrue();
});

it('gives up on a page the agent rejects', function () {
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

    $watchedPage = WatchedPage::factory()->create();

    try {
        RunCreep::dispatchSync($watchedPage);
    } catch (RequestException) {
        // The queue would retry; here we only care what was written down.
    }

    $run = $watchedPage->runs()->sole();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->not->toBeNull();
});

it('refuses to run without an agent endpoint', function () {
    config(['creeping.drivers.http.endpoint' => null]);

    httpDriver()->creep(CreepRun::factory()->running()->create());
})->throws(RuntimeException::class, 'No creeping agent endpoint is configured. Set CREEP_AGENT_ENDPOINT.');
