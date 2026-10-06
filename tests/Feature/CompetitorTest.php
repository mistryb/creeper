<?php

use App\Actions\RunBusinessAnalysis;
use App\Ai\Agents\BusinessAnalysisAgent;
use App\Enums\RunStatus;
use App\Jobs\AnalyzeBusiness;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use App\Models\User;
use App\Models\WatchedPage;
use Database\Factories\BusinessAnalysisFactory;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

describe('managing competitors', function () {
    it('lists a business\'s competitors with how many pages each is watched on', function () {
        $business = Business::factory()->create();
        $competitor = Competitor::factory()->for($business)->create();
        WatchedPage::factory()->count(2)->for($competitor)->create();
        Competitor::factory()->create();

        $this->actingAs($business->user)
            ->get(route('businesses.competitors.index', $business))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('competitors/index')
                ->has('competitors.data', 1)
                ->where('competitors.data.0.id', $competitor->id)
                ->where('competitors.data.0.watched_pages_count', 2)
            );
    });

    it('adds a competitor to a business', function () {
        $business = Business::factory()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.competitors.store', $business), [
                'name' => 'Acme Roasters',
                'url' => 'https://acme.example',
                'description' => 'The big national roaster.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $competitor = $business->competitors()->sole();

        expect($competitor->name)->toBe('Acme Roasters')
            ->and($competitor->url)->toBe('https://acme.example')
            ->and($competitor->description)->toBe('The big national roaster.');
    });

    it('needs only a name', function () {
        $business = Business::factory()->create();

        $this->actingAs($business->user)
            ->post(route('businesses.competitors.store', $business), ['name' => 'Acme', 'url' => '', 'description' => ''])
            ->assertSessionHasNoErrors();

        expect($business->competitors()->sole()->url)->toBeNull();
    });

    it('refuses a duplicate name within a business, and a website that is not public', function () {
        $business = Business::factory()->create();
        Competitor::factory()->for($business)->create(['name' => 'Acme']);

        $this->actingAs($business->user)
            ->post(route('businesses.competitors.store', $business), ['name' => 'Acme', 'url' => 'http://localhost'])
            ->assertSessionHasErrors(['name', 'url']);
    });

    it('shows a competitor and the pages watched on them', function () {
        $competitor = Competitor::factory()->create();
        $mine = WatchedPage::factory()->for($competitor)->create();
        WatchedPage::factory()->create();

        $this->actingAs($competitor->owner())
            ->get(route('competitors.show', $competitor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('competitors/show')
                ->where('competitor.data.id', $competitor->id)
                ->where('business.data.id', $competitor->business_id)
                ->has('watchedPages.data', 1)
                ->where('watchedPages.data.0.id', $mine->id)
            );
    });

    it('updates a competitor', function () {
        $competitor = Competitor::factory()->create(['name' => 'Acme']);

        $this->actingAs($competitor->owner())
            ->put(route('competitors.update', $competitor), [
                'name' => 'Acme',
                'url' => 'https://acme.example',
                'description' => 'Now sells subscriptions too.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('competitors.show', $competitor));

        expect($competitor->fresh()->description)->toBe('Now sells subscriptions too.');
    });

    it('deletes a competitor with its pages and analyses', function () {
        $competitor = Competitor::factory()->create();
        WatchedPage::factory()->for($competitor)->create();
        $analysis = BusinessAnalysis::factory()->forSubject($competitor)->create();

        $this->actingAs($competitor->owner())
            ->delete(route('competitors.destroy', $competitor))
            ->assertRedirect(route('businesses.competitors.index', $competitor->business_id));

        expect($competitor->fresh())->toBeNull()
            ->and(WatchedPage::query()->count())->toBe(0)
            ->and($analysis->fresh())->toBeNull();
    });

    it('keeps other people out', function () {
        $competitor = Competitor::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('businesses.competitors.index', $competitor->business))->assertForbidden();
        $this->actingAs($stranger)->post(route('businesses.competitors.store', $competitor->business), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($stranger)->get(route('competitors.show', $competitor))->assertForbidden();
        $this->actingAs($stranger)->put(route('competitors.update', $competitor), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('competitors.destroy', $competitor))->assertForbidden();

        expect($competitor->fresh()->name)->not->toBe('Mine');
    });
});

describe('analysing a competitor', function () {
    it('shows the competitor\'s analysis screen', function () {
        $competitor = Competitor::factory()->create();
        $analysis = BusinessAnalysis::factory()->forSubject($competitor)->create();

        $this->actingAs($competitor->owner())
            ->get(route('competitors.analysis.index', $competitor))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analysis/show')
                ->where('subject.type', 'competitor')
                ->where('subject.id', $competitor->id)
                ->where('business.data.id', $competitor->business_id)
                ->where('analysis.data.id', $analysis->id)
            );
    });

    it('queues a run on the competitor', function () {
        Queue::fake();

        $competitor = Competitor::factory()->create();
        $key = ApiKey::factory()->for($competitor->owner())->create();

        $this->actingAs($competitor->owner())
            ->post(route('competitors.analysis.store', $competitor), ['api_key_id' => $key->id])
            ->assertRedirect(route('competitors.analysis.index', $competitor));

        expect($competitor->analyses()->sole()->status)->toBe(RunStatus::Queued);
        Queue::assertPushed(AnalyzeBusiness::class);
    });

    it('reads the competitor against the user\'s own business', function () {
        BusinessAnalysisAgent::fake([BusinessAnalysisFactory::report()]);

        $business = Business::factory()->create(['description' => 'We roast to order.']);
        $competitor = Competitor::factory()->for($business)->create(['url' => null, 'description' => 'Sold in supermarkets.']);
        $analysis = BusinessAnalysis::factory()->forSubject($competitor)->queued()->create([
            'api_key_id' => ApiKey::factory()->for($business->user)->create()->id,
        ]);

        app(RunBusinessAnalysis::class)->handle($analysis);

        expect($analysis->fresh()->status)->toBe(RunStatus::Succeeded);

        BusinessAnalysisAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Sold in supermarkets.')
            && $prompt->contains('a competitor of the user\'s business')
            && $prompt->contains('We roast to order.'));
    });

    it('keeps other people out', function () {
        Queue::fake();

        $competitor = Competitor::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('competitors.analysis.index', $competitor))->assertForbidden();
        $this->actingAs($stranger)
            ->post(route('competitors.analysis.store', $competitor), ['api_key_id' => ApiKey::factory()->for($stranger)->create()->id])
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});

it('takes every analysis down with a business, including its competitors\'', function () {
    $business = Business::factory()->create();
    $competitor = Competitor::factory()->for($business)->create();
    $kept = BusinessAnalysis::factory()->create();

    BusinessAnalysis::factory()->forSubject($business)->create();
    BusinessAnalysis::factory()->forSubject($competitor)->create();

    $business->delete();

    expect(BusinessAnalysis::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('takes every analysis down with an account', function () {
    $competitor = Competitor::factory()->create();
    BusinessAnalysis::factory()->forSubject($competitor)->create();
    BusinessAnalysis::factory()->forSubject($competitor->business)->create();

    $competitor->owner()->delete();

    expect(BusinessAnalysis::query()->count())->toBe(0);
});
