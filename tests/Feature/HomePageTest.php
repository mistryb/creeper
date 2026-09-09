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
