<?php

use App\Models\ApiKey;
use App\Models\Business;
use App\Models\Competitor;
use App\Models\CompetitorDiscovery;
use App\Models\Digest;
use App\Models\User;

it('lists a company\'s competitors, saying which ones Creeper found', function () {
    $business = Business::factory()->create();
    $found = Competitor::factory()->for($business)->discovered()->create();
    $mine = Competitor::factory()->for($business)->create();
    Competitor::factory()->create();

    $this->actingAs($business->user)
        ->get(route('businesses.competitors.index', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('competitors/index')
            ->has('competitors.data', 2)
            ->where('competitors.data.0.id', $found->id)
            ->where('competitors.data.0.reason', $found->reason)
            ->where('competitors.data.1.id', $mine->id)
            ->where('competitors.data.1.discovered_at', null)
        );
});

it('says whether Creeper is looking for more, and why the last look failed', function () {
    $business = Business::factory()->create();
    $failed = CompetitorDiscovery::factory()->for($business)->failed()->create();

    $this->actingAs($business->user)
        ->get(route('businesses.competitors.index', $business))
        ->assertInertia(fn ($page) => $page
            ->where('latestDiscovery.data.id', $failed->id)
            ->where('latestDiscovery.data.status', 'failed')
            ->where('isDiscovering', false)
        );

    CompetitorDiscovery::factory()->for($business)->queued()->create();

    $this->actingAs($business->user)
        ->get(route('businesses.competitors.index', $business))
        ->assertInertia(fn ($page) => $page->where('isDiscovering', true));
});

it('says whether there is another key to pick once the company has lost its own', function () {
    $business = Business::factory()->create(['api_key_id' => null]);

    $this->actingAs($business->user)
        ->get(route('businesses.competitors.index', $business))
        ->assertInertia(fn ($page) => $page
            ->where('business.data.api_key_id', null)
            ->where('hasApiKeys', false)
        );

    ApiKey::factory()->for($business->user)->create();

    $this->actingAs($business->user)
        ->get(route('businesses.competitors.index', $business))
        ->assertInertia(fn ($page) => $page->where('hasApiKeys', true));
});

it('adds a competitor to a company', function () {
    $business = Business::factory()->create();

    $this->actingAs($business->user)
        ->post(route('businesses.competitors.store', $business), [
            'name' => 'Acme Roasters',
            'url' => 'https://acme.example',
            'description' => 'The big national roaster.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('businesses.competitors.index', $business));

    $competitor = $business->competitors()->sole();

    expect($competitor->name)->toBe('Acme Roasters')
        ->and($competitor->url)->toBe('https://acme.example')
        ->and($competitor->description)->toBe('The big national roaster.')
        ->and($competitor->discovered_at)->toBeNull();
});

it('needs only a name', function () {
    $business = Business::factory()->create();

    $this->actingAs($business->user)
        ->post(route('businesses.competitors.store', $business), ['name' => 'Acme', 'url' => '', 'description' => ''])
        ->assertSessionHasNoErrors();

    expect($business->competitors()->sole()->url)->toBeNull();
});

it('refuses a duplicate name within a company, and a website that is not public', function () {
    $business = Business::factory()->create();
    Competitor::factory()->for($business)->create(['name' => 'Acme']);

    $this->actingAs($business->user)
        ->post(route('businesses.competitors.store', $business), ['name' => 'Acme', 'url' => 'http://localhost'])
        ->assertSessionHasErrors(['name', 'url']);
});

it('shows a competitor', function () {
    $competitor = Competitor::factory()->discovered()->create();

    $this->actingAs($competitor->owner())
        ->get(route('competitors.show', $competitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('competitors/show')
            ->where('competitor.data.id', $competitor->id)
            ->where('competitor.data.reason', $competitor->reason)
            ->where('business.data.id', $competitor->business_id)
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

it('deletes a competitor, leaving the digests that mention them', function () {
    $competitor = Competitor::factory()->create();
    $digest = Digest::factory()->for($competitor->business)->create();

    $this->actingAs($competitor->owner())
        ->delete(route('competitors.destroy', $competitor))
        ->assertRedirect(route('businesses.competitors.index', $competitor->business_id));

    expect($competitor->fresh())->toBeNull()
        ->and($digest->fresh())->not->toBeNull();
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

it('goes with its company, and with the account', function () {
    $competitor = Competitor::factory()->create();

    $competitor->owner()->delete();

    expect($competitor->fresh())->toBeNull();
});
