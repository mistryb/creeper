<?php

use App\Models\Business;
use App\Models\User;

it('sends guests to log in', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows an account with no company the way in', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

it('sends an account with a company straight to its digests', function () {
    $business = Business::factory()->create();

    $this->actingAs($business->user)
        ->get(route('dashboard'))
        ->assertRedirect(route('businesses.digests.index', $business));
});

it('sends you to the digests of the company picked in the header', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create();
    $picked = Business::factory()->for($user)->create();
    $user->switchBusiness($picked);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('businesses.digests.index', $picked));
});
