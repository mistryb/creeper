<?php

use App\Actions\DiscoverCompetitors;
use App\Ai\Agents\CompetitorDiscoveryAgent;
use App\Enums\RunStatus;
use App\Jobs\RunCompetitorDiscovery;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\CompetitorDiscovery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * A queued discovery for a company with a key of its owner's.
 */
function queuedDiscovery(array $business = []): CompetitorDiscovery
{
    $business = Business::factory()->withKey()->create(['url' => null, ...$business]);

    $discovery = new CompetitorDiscovery;
    $discovery->forceFill(['api_key_id' => $business->api_key_id, 'status' => RunStatus::Queued]);
    $business->discoveries()->save($discovery);

    return $discovery;
}

/**
 * What the model proposes.
 */
function proposals(?array $competitors = null): array
{
    return [
        'competitors' => $competitors ?? [
            ['name' => 'Acme Insights', 'url' => 'https://acme.example', 'reason' => 'The incumbent.'],
            ['name' => 'Widgets Inc', 'url' => 'https://www.widgets.example/', 'reason' => 'A newer rival.'],
        ],
        'notes' => null,
    ];
}

describe('running discovery', function () {
    it('adds every competitor the model proposes', function () {
        CompetitorDiscoveryAgent::fake([proposals()]);

        $discovery = queuedDiscovery();

        app(DiscoverCompetitors::class)->handle($discovery);

        $discovery->refresh();
        $competitors = $discovery->business->competitors()->get();

        expect($discovery->status)->toBe(RunStatus::Succeeded)
            ->and($competitors)->toHaveCount(2)
            ->and($competitors[0]->name)->toBe('Acme Insights')
            ->and($competitors[0]->url)->toBe('https://acme.example')
            ->and($competitors[0]->reason)->toBe('The incumbent.')
            ->and($competitors[0]->discovered_at)->not->toBeNull()
            ->and($discovery->report['proposals'])->toHaveCount(2)
            ->and($discovery->report['proposals'][1]['added'])->toBeTrue()
            ->and($discovery->provider)->toBe('anthropic')
            ->and($discovery->prompt_tokens)->toBeInt();
    });

    it('searches from the owner\'s description and the competitors already listed', function () {
        CompetitorDiscoveryAgent::fake([proposals()]);

        $discovery = queuedDiscovery(['description' => 'Product analytics for small SaaS teams.']);
        Competitor::factory()->for($discovery->business)->create(['name' => 'Already Here']);

        app(DiscoverCompetitors::class)->handle($discovery);

        CompetitorDiscoveryAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Product analytics for small SaaS teams.')
            && $prompt->contains('Already Here')
            && $prompt->contains('No website could be read'));
    });

    it('skips the company itself and anyone already on the list, by name or by host', function () {
        CompetitorDiscoveryAgent::fake([proposals([
            ['name' => 'Northwind', 'url' => 'https://other.example', 'reason' => 'Itself, by name.'],
            ['name' => 'Someone Else', 'url' => 'https://www.northwind.example', 'reason' => 'Itself, by host.'],
            ['name' => 'ACME INSIGHTS', 'url' => null, 'reason' => 'Already listed, by name.'],
            ['name' => 'Widgets', 'url' => 'https://widgets.example/pricing', 'reason' => 'Already listed, by host.'],
            ['name' => 'Genuinely New', 'url' => 'https://new.example', 'reason' => 'New.'],
        ])]);

        $discovery = queuedDiscovery(['name' => 'Northwind', 'url' => 'https://northwind.example']);
        Competitor::factory()->for($discovery->business)->create(['name' => 'Acme Insights', 'url' => null]);
        Competitor::factory()->for($discovery->business)->create(['name' => 'Widgets Inc', 'url' => 'https://www.widgets.example']);

        Http::fake(['northwind.example/*' => Http::response('', 404)]);

        app(DiscoverCompetitors::class)->handle($discovery);

        $added = collect($discovery->fresh()->report['proposals'])->where('added', true)->pluck('name')->all();

        expect($added)->toBe(['Genuinely New'])
            ->and($discovery->business->competitors()->count())->toBe(3);
    });

    it('never lets the list grow past the cap', function () {
        config(['digest.discovery.max_competitors' => 3]);

        CompetitorDiscoveryAgent::fake([proposals([
            ['name' => 'One', 'url' => null, 'reason' => 'r'],
            ['name' => 'Two', 'url' => null, 'reason' => 'r'],
            ['name' => 'Three', 'url' => null, 'reason' => 'r'],
        ])]);

        $discovery = queuedDiscovery();
        Competitor::factory()->for($discovery->business)->create(['name' => 'Existing']);

        app(DiscoverCompetitors::class)->handle($discovery);

        expect($discovery->business->competitors()->count())->toBe(3)
            ->and(collect($discovery->fresh()->report['proposals'])->where('added', true))->toHaveCount(2);
    });

    it('drops a proposed website that is not a public address', function () {
        CompetitorDiscoveryAgent::fake([proposals([
            ['name' => 'Inside Job', 'url' => 'http://192.168.1.10', 'reason' => 'r'],
        ])]);

        $discovery = queuedDiscovery();

        app(DiscoverCompetitors::class)->handle($discovery);

        expect($discovery->business->competitors()->sole()->url)->toBeNull();
    });

    it('spends the company\'s key, and only for the length of the call', function () {
        $apiKey = ApiKey::factory()->value('sk-ant-api03-user-secret-abcd')->create();
        $business = Business::factory()->for($apiKey->user)->create(['url' => null, 'api_key_id' => $apiKey->id]);

        $during = null;

        CompetitorDiscoveryAgent::fake(function (string $prompt) use ($apiKey, &$during): array {
            $during = config("ai.providers.creep_key_{$apiKey->id}");

            return proposals();
        });

        $discovery = new CompetitorDiscovery;
        $discovery->forceFill(['api_key_id' => $apiKey->id, 'status' => RunStatus::Queued]);
        $business->discoveries()->save($discovery);

        app(DiscoverCompetitors::class)->handle($discovery);

        expect($during)->toBe(['driver' => 'anthropic', 'key' => 'sk-ant-api03-user-secret-abcd'])
            ->and(config("ai.providers.creep_key_{$apiKey->id}"))->toBeNull();
    });

    it('fails, saying so, when the key has been removed', function () {
        CompetitorDiscoveryAgent::fake([proposals()]);

        $discovery = queuedDiscovery();
        $discovery->apiKey->delete();

        app(DiscoverCompetitors::class)->handle($discovery->fresh());

        expect($discovery->fresh()->status)->toBe(RunStatus::Failed)
            ->and($discovery->fresh()->error)->toContain('removed');

        CompetitorDiscoveryAgent::assertNeverPrompted();
    });

    it('turns a provider refusal into a sentence', function () {
        CompetitorDiscoveryAgent::fake(fn (string $prompt) => throw new InsufficientCreditsException('no credit'));

        $discovery = queuedDiscovery();

        app(DiscoverCompetitors::class)->handle($discovery);

        expect($discovery->fresh()->status)->toBe(RunStatus::Failed)
            ->and($discovery->fresh()->error)->toContain('no credit left');
    });

    it('fails when the model answers with the wrong shape', function () {
        CompetitorDiscoveryAgent::fake([['competitors' => [['url' => 'https://x.example']]]]);

        $discovery = queuedDiscovery();

        app(DiscoverCompetitors::class)->handle($discovery);

        expect($discovery->fresh()->status)->toBe(RunStatus::Failed)
            ->and($discovery->business->competitors()->count())->toBe(0);
    });

    it('marks a run that died mid-way as failed', function () {
        $discovery = queuedDiscovery();
        $discovery->markRunning();

        (new RunCompetitorDiscovery($discovery))->failed(new RuntimeException('worker died'));

        expect($discovery->fresh()->status)->toBe(RunStatus::Failed);
    });
});

describe('the button', function () {
    it('queues a discovery for the company', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.discovery.store', $business))
            ->assertRedirect();

        expect($business->discoveries()->sole()->status)->toBe(RunStatus::Queued)
            ->and($business->discoveries()->sole()->api_key_id)->toBe($business->api_key_id);

        Queue::assertPushed(RunCompetitorDiscovery::class);
    });

    it('refuses while one is already running, and without a key', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create();
        CompetitorDiscovery::factory()->for($business)->queued()->create();

        $this->actingAs($business->user)->post(route('businesses.discovery.store', $business));

        expect($business->discoveries()->count())->toBe(1);

        $keyless = Business::factory()->for($business->user)->create();

        $this->actingAs($business->user)->post(route('businesses.discovery.store', $keyless));

        expect($keyless->discoveries()->count())->toBe(0);

        Queue::assertNothingPushed();
    });

    it('keeps other people out', function () {
        Queue::fake();

        $business = Business::factory()->withKey()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('businesses.discovery.store', $business))
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});
