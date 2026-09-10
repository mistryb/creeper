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

/*
 * The page offers the prompt twice — once in the block and once in the
 * floating dock — and the two have to be the same text, so the source is
 * asserted rather than the response: the prompt is a constant in the React
 * page, and a rendered Inertia payload never carries it.
 */
it('hands the floating copy button the same prompt as the block', function () {
    $page = file_get_contents(resource_path('js/pages/deploy.tsx'));

    expect(substr_count($page, 'const PROMPT ='))->toBe(1)
        ->and(substr_count($page, 'text={PROMPT}'))->toBe(2);
});

/*
 * The dock is the page's one standing offer, so it renders outside the layout
 * and behind no condition — a visitor who never scrolls as far as the block
 * still has the prompt within reach.
 */
it('keeps the floating copy button on the page unconditionally', function () {
    $page = file_get_contents(resource_path('js/pages/deploy.tsx'));

    expect(substr_count($page, '<CopyDock'))->toBe(1)
        ->and($page)->toContain("</MarketingLayout>\n\n            <CopyDock");
});
