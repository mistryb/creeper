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
it('pitches the landing page as a competitor digest', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($page)
        ->toContain('Always be creeping')
        ->toContain('Know what your rivals')
        ->not->toContain('Put a page on watch')
        ->not->toContain('watched page');
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

it('sells the plans on the landing page of a billed install', function () {
    config(['marketing.enabled' => true]);
    enableBilling();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('plans', 2)
            ->where('plans.0.price', 5)
            ->where('plans.1.price', 20)
            ->where('trialDays', 7));
});

it('pitches running your own copy when the install has nothing to sell', function () {
    config(['marketing.enabled' => true]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('plans', null));
});

/*
 * The homepage is the one public page with a way back in: a Log in pill in its
 * own tab bar, for visitors who are not signed in. The shared chrome still has
 * none (see above), so the deploy page keeps pointing at its own prompt.
 */
it('offers signed-out visitors a log in pill in the homepage tab bar', function () {
    $page = file_get_contents(resource_path('js/pages/welcome.tsx'));

    expect($page)
        ->toContain('{!auth.user && (')
        ->toContain('href={login()}')
        ->toContain('Log in');
});

/*
 * Link previews on Twitter/X, Slack and iMessage come from the server-rendered
 * shell — their crawlers never run the React app — so the tags are asserted on
 * the raw response.
 */
it('unfurls into a large image card when the landing page is shared', function () {
    config(['marketing.enabled' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false)
        ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', escape: false)
        ->assertSee('<meta property="og:title"', escape: false)
        ->assertSee('<meta name="twitter:image:alt"', escape: false);
});

it('ships a share image at the size the cards crop to', function () {
    [$width, $height] = getimagesize(public_path('og-image.png'));

    expect([$width, $height])->toBe([1200, 630]);
});
