<?php

use App\Models\Business;
use App\Models\Competitor;
use App\Models\CompetitorDiscovery;
use App\Models\Digest;
use App\Models\User;
use Database\Factories\DigestFactory;

it('sends guests to log in', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows an account with no company the way in', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard')->where('business', null));
});

it('shows the chosen company, its competitors, and the latest digest', function () {
    $business = Business::factory()->withKey()->create();
    $competitor = Competitor::factory()->for($business)->discovered()->create();
    Digest::factory()->for($business)->create(['report' => DigestFactory::report([$competitor])]);
    $failed = Digest::factory()->for($business)->failed()->create();
    CompetitorDiscovery::factory()->for($business)->queued()->create();

    $this->actingAs($business->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('business.data.id', $business->id)
            ->where('business.data.api_key_label', $business->apiKey->label())
            ->has('competitors.data', 1)
            ->where('competitors.data.0.reason', $competitor->reason)
            ->where('digest.data.report.headline', 'Prices went up across the board this week.')
            ->where('latestDigestRun.data.id', $failed->id)
            ->where('isDigesting', false)
            ->where('isDiscovering', true)
            ->where('hasApiKeys', true)
        );
});
