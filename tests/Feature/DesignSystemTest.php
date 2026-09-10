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

/*
 * A design system component that is not on the style guide gets reinvented
 * inside the next screen that needs it. CopyDock is fixed to the viewport, so
 * the specimen pins it in the page with `static`.
 */
it('carries a specimen for the copy dock', function () {
    expect(file_get_contents(resource_path('js/pages/design.tsx')))
        ->toContain('<CopyDock');
});

/*
 * A ribbon colour that is not in `@theme` is not an error: Tailwind emits no
 * rule for the class and the element simply renders unstyled, which is a hard
 * thing to notice in review. Every ribbon a component asks for is checked to
 * have a value, hover companions like `ribbon-amber-lit` included.
 */
it('gives every ribbon colour a component asks for a value', function () {
    preg_match_all(
        '/--color-(ribbon(?:-[a-z]+)*):/',
        file_get_contents(resource_path('css/app.css')),
        $declared,
    );

    $used = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file) => in_array($file->getExtension(), ['ts', 'tsx']))
        ->flatMap(function ($file) {
            preg_match_all(
                '/(?:bg|text|border|ring|decoration|fill|stroke)-(ribbon(?:-[a-z]+)*)/',
                $file->getContents(),
                $matches,
            );

            return $matches[1];
        })
        ->unique()
        ->values();

    expect($used)->not->toBeEmpty()
        ->and($used->diff($declared[1])->all())->toBe([]);
});
