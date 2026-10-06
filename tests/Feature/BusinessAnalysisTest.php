<?php

use App\Actions\RunBusinessAnalysis;
use App\Ai\Agents\BusinessAnalysisAgent;
use App\Enums\RunStatus;
use App\Jobs\AnalyzeBusiness;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\User;
use Database\Factories\BusinessAnalysisFactory;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A queued analysis of a business, paid for by one of its owner's keys.
 */
function queuedAnalysis(array $business = []): BusinessAnalysis
{
    $business = Business::factory()->create($business);
    $key = ApiKey::factory()->for($business->user)->create();

    return BusinessAnalysis::factory()->forBusiness($business)->queued()->create(['api_key_id' => $key->id]);
}

describe('the screen', function () {
    it('shows a business with no analysis yet', function () {
        $business = Business::factory()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.analysis.index', $business))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analysis/show')
                ->where('subject.type', 'business')
                ->where('business.data.id', $business->id)
                ->where('analysis', null)
                ->has('history.data', 0)
                ->where('isAnalyzing', false)
            );
    });

    it('shows the newest successful report, with every run in the history', function () {
        $business = Business::factory()->create();
        $older = BusinessAnalysis::factory()->forBusiness($business)->create();
        $newer = BusinessAnalysis::factory()->forBusiness($business)->create();
        BusinessAnalysis::factory()->forBusiness($business)->failed()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.analysis.index', $business))
            ->assertInertia(fn ($page) => $page
                ->where('analysis.data.id', $newer->id)
                ->where('analysis.data.report.summary', BusinessAnalysisFactory::report()['summary'])
                ->has('history.data', 3)
            );

        expect($older->exists)->toBeTrue();
    });

    it('shows an earlier report when one is picked from the history', function () {
        $business = Business::factory()->create();
        $older = BusinessAnalysis::factory()->forBusiness($business)->create();
        BusinessAnalysis::factory()->forBusiness($business)->create();

        $this->actingAs($business->user)
            ->get(route('businesses.analysis.index', [$business, 'analysis' => $older->id]))
            ->assertInertia(fn ($page) => $page->where('analysis.data.id', $older->id));
    });

    it('will not show another business\'s report through the picker', function () {
        $business = Business::factory()->create();
        $mine = BusinessAnalysis::factory()->forBusiness($business)->create();
        $theirs = BusinessAnalysis::factory()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.analysis.index', [$business, 'analysis' => $theirs->id]))
            ->assertInertia(fn ($page) => $page->where('analysis.data.id', $mine->id));
    });

    it('says when a run is in flight', function () {
        $business = Business::factory()->create();
        BusinessAnalysis::factory()->forBusiness($business)->queued()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.analysis.index', $business))
            ->assertInertia(fn ($page) => $page->where('isAnalyzing', true));
    });

    it('keeps other people out', function () {
        $business = Business::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('businesses.analysis.index', $business))
            ->assertForbidden();
    });
});

describe('starting a run', function () {
    it('queues an analysis on the chosen key', function () {
        Queue::fake();

        $business = Business::factory()->create();
        $key = ApiKey::factory()->for($business->user)->create();

        $this->actingAs($business->user)
            ->post(route('businesses.analysis.store', $business), ['api_key_id' => $key->id])
            ->assertRedirect(route('businesses.analysis.index', $business));

        $analysis = $business->analyses()->sole();

        expect($analysis->status)->toBe(RunStatus::Queued)
            ->and($analysis->api_key_id)->toBe($key->id);

        Queue::assertPushed(AnalyzeBusiness::class, fn (AnalyzeBusiness $job): bool => $job->analysis->is($analysis));
    });

    it('refuses somebody else\'s key', function () {
        Queue::fake();

        $business = Business::factory()->create();
        $theirs = ApiKey::factory()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.analysis.store', $business), ['api_key_id' => $theirs->id])
            ->assertSessionHasErrors('api_key_id');

        Queue::assertNothingPushed();
    });

    it('will not start a second run while one is going', function () {
        Queue::fake();

        $business = Business::factory()->create();
        $key = ApiKey::factory()->for($business->user)->create();
        BusinessAnalysis::factory()->forBusiness($business)->queued()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.analysis.store', $business), ['api_key_id' => $key->id]);

        expect($business->analyses()->count())->toBe(1);
        Queue::assertNothingPushed();
    });

    it('will not run on somebody else\'s business', function () {
        Queue::fake();

        $business = Business::factory()->create();
        $stranger = User::factory()->create();
        $key = ApiKey::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->post(route('businesses.analysis.store', $business), ['api_key_id' => $key->id])
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});

describe('the run', function () {
    beforeEach(function () {
        Http::preventStrayRequests();

        config(['creeping.drivers.llm.pin_address' => false]);
    });

    it('stores the report the model returns', function () {
        BusinessAnalysisAgent::fake([BusinessAnalysisFactory::report()]);

        $analysis = queuedAnalysis(['url' => null]);

        app(RunBusinessAnalysis::class)->handle($analysis);

        $analysis->refresh();

        expect($analysis->status)->toBe(RunStatus::Succeeded)
            ->and($analysis->report['summary'])->toBe(BusinessAnalysisFactory::report()['summary'])
            ->and($analysis->report['strengths'])->toBe(['Freshness guarantee'])
            ->and($analysis->source_url)->toBeNull()
            ->and($analysis->finished_at)->not->toBeNull();

        BusinessAnalysisAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains($analysis->analyzable->description)
            && $prompt->contains('No website was read'));
    });

    it('reads the website alongside the description', function () {
        Http::fake([
            'northwind.example/*' => Http::response(
                '<html><head><meta name="description" content="Roasted to order."></head><body><h1>Northwind Coffee</h1><p>'
                .str_repeat('Single-origin coffee subscriptions, roasted to order and shipped within 48 hours. ', 5)
                .'</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
        BusinessAnalysisAgent::fake([BusinessAnalysisFactory::report()]);

        $analysis = queuedAnalysis(['url' => 'https://northwind.example/']);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->source_url)->toBe('https://northwind.example/');

        BusinessAnalysisAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('shipped within 48 hours'));
    });

    it('carries on without a website that cannot be read', function () {
        Http::fake(['northwind.example/*' => Http::response('', 500)]);
        BusinessAnalysisAgent::fake([BusinessAnalysisFactory::report()]);

        $analysis = queuedAnalysis(['url' => 'https://northwind.example/']);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->status)->toBe(RunStatus::Succeeded)
            ->and($analysis->fresh()->source_url)->toBeNull();
    });

    it('spends the chosen key, and only for the length of the call', function () {
        $analysis = queuedAnalysis(['url' => null]);
        $instance = "ai.providers.creep_key_{$analysis->api_key_id}";
        $during = null;

        BusinessAnalysisAgent::fake(function (string $prompt) use ($instance, &$during): array {
            $during = config($instance);

            return BusinessAnalysisFactory::report();
        });

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($during['key'])->toBe($analysis->apiKey->key)
            ->and(config($instance))->toBeNull();
    });

    it('fails, saying why, when the key has no credit', function () {
        BusinessAnalysisAgent::fake(fn (string $prompt) => throw new InsufficientCreditsException('No credit.'));

        $analysis = queuedAnalysis(['url' => null]);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->status)->toBe(RunStatus::Failed)
            ->and($analysis->fresh()->error)->toContain('no credit left');
    });

    it('fails, saying why, when the provider rejects the key', function () {
        BusinessAnalysisAgent::fake(fn (string $prompt) => throw new RequestException(
            new Response(new GuzzleResponse(401, [], 'unauthorized')),
        ));

        $analysis = queuedAnalysis(['url' => null]);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->error)->toContain('rejected this API key');
    });

    it('fails when the key it was to spend has been deleted', function () {
        BusinessAnalysisAgent::fake()->preventStrayPrompts();

        $analysis = queuedAnalysis(['url' => null]);
        $analysis->apiKey->delete();

        app(RunBusinessAnalysis::class)->handle($analysis->fresh());

        expect($analysis->fresh()->status)->toBe(RunStatus::Failed);
        BusinessAnalysisAgent::assertNeverPrompted();
    });

    it('refuses a report that is not the expected shape', function () {
        BusinessAnalysisAgent::fake([[...BusinessAnalysisFactory::report(), 'confidence' => 'certain']]);

        $analysis = queuedAnalysis(['url' => null]);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->status)->toBe(RunStatus::Failed)
            ->and($analysis->fresh()->report)->toBeNull();
    });

    it('never leaves a run spinning when the job dies', function () {
        $analysis = queuedAnalysis();

        (new AnalyzeBusiness($analysis))->failed(new RuntimeException('Worker timed out.'));

        expect($analysis->fresh()->status)->toBe(RunStatus::Failed);
    });
});

it('keeps a business\'s analyses with it, and goes when it does', function () {
    $analysis = BusinessAnalysis::factory()->create();

    expect($analysis->analyzable)->toBeInstanceOf(Business::class)
        ->and($analysis->analyzable_type)->toBe('business');
});
