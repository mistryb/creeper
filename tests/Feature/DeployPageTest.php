<?php

use App\Models\User;

it('shows the deploy walkthrough in marketing mode', function () {
    config(['marketing.enabled' => true]);

    $this->get(route('deploy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deploy'));
});

it('keeps the deploy walkthrough reachable once you are signed in', function () {
    config(['marketing.enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('deploy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deploy'));
});

/*
 * The page is there to be handed to someone who has not installed Creeper yet.
 * An install with no public face has no such visitor, so it does not answer at
 * all rather than pitching a deployment to its own users.
 */
it('does not exist when marketing mode is off', function () {
    $this->get(route('deploy'))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('deploy'))
        ->assertNotFound();
});
