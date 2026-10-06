<?php

use App\Models\User;

it('serves the sign-in page from the front door by default', function () {
    $this->get(route('home'))
        ->assertRedirect(route('login'));
});

it('sends a signed-in visitor on to the dashboard when marketing mode is off', function () {
    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

it('shows the landing page to visitors in marketing mode', function () {
    config(['marketing.enabled' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('keeps the landing page reachable once you are signed in', function () {
    config(['marketing.enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome'));
});

it('loads all three design system typefaces everywhere', function () {
    config(['marketing.enabled' => true]);

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

/*
 * The chrome the public pages share offers no way to sign in: the landing page
 * sends a visitor to its own sign-up call to action instead of competing with
 * it from the header and footer. Asserted against the source because the React
 * layout is never rendered by the test suite — the same reason the style guide
 * asserts on routes/web.php rather than on a response.
 */
it('offers no sign-in link in the marketing chrome', function () {
    $layout = file_get_contents(resource_path('js/layouts/marketing-layout.tsx'));

    expect($layout)
        ->not->toContain('Log in')
        ->not->toContain('login');
});

/*
 * Creeper is pitched as a competitor watcher, not a general page watcher.
 * Asserted against the source for the same reason as the chrome above: the
 * React page is never rendered by the test suite.
 */
it('pitches the landing page as a competitor watcher', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($page)
        ->toContain('Competitor watcher')
        ->toContain('Keep tabs on your competitors')
        ->not->toContain('Put a page on watch');
});

it('has no sign-up call to action in the landing page hero', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($page)
        ->not->toContain('Creep it')
        ->not->toContain('<form');
});

it('links to the repository with a github button instead of a run option card', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($page)
        ->toContain('View on GitHub')
        ->toContain('<Cloud aria-hidden="true" />')
        ->toContain('href={REPOSITORY_URL}')
        ->not->toContain('Read the source')
        ->not->toContain('Put it on Laravel Cloud');
});

it('offers the github and laravel cloud buttons in the hero and at the foot of the page', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect(substr_count($page, 'Deploy on Laravel Cloud'))->toBe(1)
        ->and(substr_count($page, '<RunButtons '))->toBe(2);
});
