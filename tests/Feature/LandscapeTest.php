<?php

use App\Actions\RunLandscapeAnalysis;
use App\Ai\Agents\LandscapeAgent;
use App\Enums\PageCategory;
use App\Enums\RunStatus;
use App\Jobs\RunLandscape;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use App\Models\LandscapeAnalysis;
use App\Models\PageSnapshot;
use App\Models\User;
use App\Models\WatchedPage;
use Database\Factories\LandscapeAnalysisFactory;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A queued comparison of a business with one competitor, on its owner's key.
 */
function queuedLandscape(): LandscapeAnalysis
{
    $business = Business::factory()->create(['name' => 'Northwind', 'description' => 'Simple analytics with a free tier.']);
    Competitor::factory()->for($business)->create(['name' => 'Acme', 'description' => 'Expensive and enterprise.']);

    return LandscapeAnalysis::factory()->for($business)->queued()->create([
        'api_key_id' => ApiKey::factory()->for($business->user)->create()->id,
    ]);
}

describe('starting a comparison', function () {
    it('queues one on the chosen key', function () {
        Queue::fake();

        $business = Business::factory()->create();
        Competitor::factory()->for($business)->create();
        $key = ApiKey::factory()->for($business->user)->create();

        $this->actingAs($business->user)
            ->post(route('businesses.landscape.store', $business), ['api_key_id' => $key->id])
            ->assertRedirect(route('dashboard'));

        $landscape = $business->landscapes()->sole();

        expect($landscape->status)->toBe(RunStatus::Queued)
            ->and($landscape->api_key_id)->toBe($key->id);

        Queue::assertPushed(RunLandscape::class, fn (RunLandscape $job): bool => $job->landscape->is($landscape));
    });

    it('will not compare with nobody to compare against', function () {
        Queue::fake();

        $business = Business::factory()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.landscape.store', $business), ['api_key_id' => ApiKey::factory()->for($business->user)->create()->id]);

        expect($business->landscapes()->count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('will not start a second while one is going', function () {
        Queue::fake();

        $business = Business::factory()->create();
        Competitor::factory()->for($business)->create();
        LandscapeAnalysis::factory()->for($business)->queued()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.landscape.store', $business), ['api_key_id' => ApiKey::factory()->for($business->user)->create()->id]);

        expect($business->landscapes()->count())->toBe(1);
        Queue::assertNothingPushed();
    });

    it('refuses somebody else\'s key and somebody else\'s business', function () {
        Queue::fake();

        $business = Business::factory()->create();
        Competitor::factory()->for($business)->create();
        $stranger = User::factory()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.landscape.store', $business), ['api_key_id' => ApiKey::factory()->create()->id])
            ->assertSessionHasErrors('api_key_id');

        $this->actingAs($stranger)
            ->post(route('businesses.landscape.store', $business), ['api_key_id' => ApiKey::factory()->for($stranger)->create()->id])
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});

describe('the run', function () {
    it('stores a comparison with a row for every company', function () {
        $landscape = queuedLandscape();
        LandscapeAgent::fake([LandscapeAnalysisFactory::reportFor($landscape->business)]);

        app(RunLandscapeAnalysis::class)->handle($landscape);

        $landscape->refresh();

        expect($landscape->status)->toBe(RunStatus::Succeeded)
            ->and($landscape->report['rows'])->toHaveCount(2)
            ->and($landscape->report['rows'][0]['subject'])->toBe('you')
            ->and($landscape->report['dimensions'][0]['name'])->toBe('Entry price');
    });

    it('shows the model everything known, each company by its subject id', function () {
        $landscape = queuedLandscape();
        $business = $landscape->business;
        $acme = $business->competitors()->sole();

        BusinessAnalysis::factory()->forSubject($acme)->create();
        $page = WatchedPage::factory()->for($acme)->create(['name' => 'Pricing', 'category' => PageCategory::Pricing]);
        PageSnapshot::factory()->of($page, ['Pro plan' => '$99/month'])->create();
        LandscapeAnalysis::factory()->for($business)->create();

        LandscapeAgent::fake([LandscapeAnalysisFactory::reportFor($business)]);

        app(RunLandscapeAnalysis::class)->handle($landscape);

        LandscapeAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('# You (subject: you)')
            && $prompt->contains('Simple analytics with a free tier.')
            && $prompt->contains("(subject: competitor:{$acme->id})")
            && $prompt->contains('Expensive and enterprise.')
            && $prompt->contains('Latest analysis:')
            && $prompt->contains('[pricing] Pricing — Pro plan: $99/month')
            && $prompt->contains('# Dimensions used last time')
            && $prompt->contains('- Entry price (fact)'));
    });

    it('spends the chosen key, and only for the length of the call', function () {
        $landscape = queuedLandscape();
        $instance = "ai.providers.creep_key_{$landscape->api_key_id}";
        $during = null;

        LandscapeAgent::fake(function (string $prompt) use ($landscape, $instance, &$during): array {
            $during = config($instance);

            return LandscapeAnalysisFactory::reportFor($landscape->business);
        });

        app(RunLandscapeAnalysis::class)->handle($landscape);

        expect($during['key'])->toBe($landscape->apiKey->key)
            ->and(config($instance))->toBeNull();
    });

    it('fails, saying why, when the provider rejects the key', function () {
        $landscape = queuedLandscape();
        LandscapeAgent::fake(fn (string $prompt) => throw new RequestException(new Response(new GuzzleResponse(401, [], 'no'))));

        app(RunLandscapeAnalysis::class)->handle($landscape);

        expect($landscape->fresh()->status)->toBe(RunStatus::Failed)
            ->and($landscape->fresh()->error)->toContain('rejected this API key');
    });

    it('fails without asking when the key has gone', function () {
        LandscapeAgent::fake()->preventStrayPrompts();

        $landscape = queuedLandscape();
        $landscape->apiKey->delete();

        app(RunLandscapeAnalysis::class)->handle($landscape->fresh());

        expect($landscape->fresh()->status)->toBe(RunStatus::Failed);
        LandscapeAgent::assertNeverPrompted();
    });

    it('never leaves a run spinning when the job dies', function () {
        $landscape = queuedLandscape();

        (new RunLandscape($landscape))->failed(new RuntimeException('Worker timed out.'));

        expect($landscape->fresh()->status)->toBe(RunStatus::Failed);
    });
});

describe('cleaning up what the model says', function () {
    it('drops companies it was not asked about and fills gaps with Unknown', function () {
        $report = app(RunLandscapeAnalysis::class)->normalise([
            'summary' => 'You are cheaper.',
            'actions' => ['Raise prices.', '', 'Ship faster.'],
            'dimensions' => [
                ['name' => 'Price', 'kind' => 'fact', 'description' => 'Entry price.'],
                ['name' => 'price', 'kind' => 'fact', 'description' => 'A duplicate.'],
                ['name' => 'Ease', 'kind' => 'opinion', 'description' => 'Ease of use.'],
            ],
            'rows' => [
                ['subject' => 'competitor:1', 'confidence' => 'certain', 'cells' => [['dimension' => 'Ease', 'value' => 'Hard', 'score' => 9]]],
                ['subject' => 'you', 'confidence' => 'high', 'cells' => [['dimension' => 'Price', 'value' => '$10', 'score' => null], ['dimension' => 'Ease', 'value' => 'Easy', 'score' => 5]]],
                ['subject' => 'competitor:99', 'confidence' => 'high', 'cells' => []],
            ],
            'map' => ['x_axis' => 'Price', 'y_axis' => 'Ease', 'points' => [
                ['subject' => 'you', 'x' => 12, 'y' => -1],
                ['subject' => 'competitor:99', 'x' => 5, 'y' => 5],
            ]],
        ], ['you', 'competitor:1']);

        expect($report['actions'])->toBe(['Raise prices.', 'Ship faster.'])
            ->and(array_column($report['dimensions'], 'name'))->toBe(['Price', 'Ease'])
            ->and($report['dimensions'][1]['kind'])->toBe('judgement')
            ->and(array_column($report['rows'], 'subject'))->toBe(['you', 'competitor:1'])
            ->and($report['rows'][1]['confidence'])->toBe('low')
            ->and($report['rows'][1]['cells'])->toBe([
                ['dimension' => 'Price', 'value' => 'Unknown', 'score' => null],
                ['dimension' => 'Ease', 'value' => 'Hard', 'score' => null],
            ])
            ->and($report['map']['points'])->toBe([['subject' => 'you', 'x' => 10.0, 'y' => 0.0]]);
    });

    it('refuses a comparison with no row for the business itself', function () {
        expect(app(RunLandscapeAnalysis::class)->normalise([
            'summary' => 'Only about them.',
            'actions' => [],
            'dimensions' => [['name' => 'Price', 'kind' => 'fact', 'description' => '']],
            'rows' => [['subject' => 'competitor:1', 'confidence' => 'high', 'cells' => []]],
            'map' => null,
        ], ['you', 'competitor:1']))->toBeNull();
    });

    it('refuses a comparison with no dimensions', function () {
        expect(app(RunLandscapeAnalysis::class)->normalise([
            'summary' => 'Nothing to compare on.',
            'dimensions' => [],
            'rows' => [['subject' => 'you', 'confidence' => 'high', 'cells' => []]],
        ], ['you']))->toBeNull();
    });
});

it('goes with its business', function () {
    $landscape = LandscapeAnalysis::factory()->create();

    $landscape->business->delete();

    expect($landscape->fresh())->toBeNull();
});
