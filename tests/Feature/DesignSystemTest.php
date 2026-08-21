<?php

use App\Models\User;

it('serves the style guide outside production', function () {
    $this->get('/design')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('design'));
});

/*
 * The guard is asserted rather than the production response: the route table
 * is built before a test can change the environment, so there is no way to ask
 * a booted application for the production shape of its own routes.
 */
it('keeps the style guide out of the route table in production', function () {
    expect(file_get_contents(base_path('routes/web.php')))
        ->toContain('! app()->isProduction()');
});

it('no longer offers an appearance setting', function () {
    expect(Route::has('appearance.edit'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get('/settings/appearance')
        ->assertNotFound();
});
