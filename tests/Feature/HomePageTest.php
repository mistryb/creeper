<?php

use App\Models\User;

it('shows the landing page to visitors', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('keeps the landing page reachable once you are signed in', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('quotes the price from config rather than from the markup', function () {
    config([
        'billing.plans.creeper.amount' => 700,
        'billing.plans.creeper.included_runs' => 1500,
        'billing.meter.unit_amount' => 1,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pricing.amount', '$7')
            ->where('pricing.period', 'month')
            ->where('pricing.included_runs', 1500)
            ->where('pricing.overage', '$0.01')
        );
});

it('follows the config when the price changes', function () {
    config(['billing.plans.creeper.amount' => 1250]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('pricing.amount', '$12.50'));
});

it('loads the landing page typefaces on the landing page only', function () {
    $this->get(route('home'))->assertSee('Doto', escape: false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Doto', escape: false);
});
