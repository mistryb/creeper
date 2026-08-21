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

it('loads all three design system typefaces everywhere', function () {
    // Mono and Doto stopped belonging to the landing page when they became
    // part of the design system: the app sets labels in one and figures in the
    // other, so both halves of the product must load the same faces.
    $this->get(route('home'))
        ->assertSee('Instrument Sans', escape: false)
        ->assertSee('IBM Plex Mono', escape: false)
        ->assertSee('Doto', escape: false);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Instrument Sans', escape: false)
        ->assertSee('IBM Plex Mono', escape: false)
        ->assertSee('Doto', escape: false);
});
