<?php

use App\Models\Business;
use App\Models\User;

it('sends guests to log in', function () {
    $this->get(route('businesses.index'))->assertRedirect(route('login'));
});

it('lists only the signed-in user\'s businesses', function () {
    $user = User::factory()->create();
    $mine = Business::factory()->for($user)->create();
    Business::factory()->create();

    $this->actingAs($user)
        ->get(route('businesses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('businesses/index')
            ->has('businesses.data', 1)
            ->where('businesses.data.0.id', $mine->id)
        );
});

it('shows the set-up form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('businesses.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('businesses/create'));
});

it('sets up a business with its description', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), [
            'name' => 'Northwind Coffee',
            'description' => 'Small-batch roaster selling subscriptions to home brewers.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $business = $user->businesses()->sole();

    expect($business->name)->toBe('Northwind Coffee')
        ->and($business->description)->toBe('Small-batch roaster selling subscriptions to home brewers.');
});

it('lets one user run several businesses', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create(['name' => 'Northwind Coffee']);

    $this->actingAs($user)
        ->post(route('businesses.store'), [
            'name' => 'Northwind Tea',
            'description' => 'Loose-leaf tea, same shop.',
        ])
        ->assertSessionHasNoErrors();

    expect($user->businesses()->count())->toBe(2);
});

it('saves the business\'s website', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), [
            'name' => 'Northwind Coffee',
            'url' => 'https://northwindcoffee.com',
            'description' => 'Small-batch roaster.',
        ])
        ->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->url)->toBe('https://northwindcoffee.com');
});

it('treats the website as optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), [
            'name' => 'Northwind Coffee',
            'url' => '',
            'description' => 'Small-batch roaster.',
        ])
        ->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->url)->toBeNull();
});

it('refuses a website that is not a public URL', function (string $url) {
    $this->actingAs(User::factory()->create())
        ->post(route('businesses.store'), [
            'name' => 'Northwind Coffee',
            'url' => $url,
            'description' => 'Small-batch roaster.',
        ])
        ->assertSessionHasErrors('url');
})->with([
    'not a url' => 'northwind coffee',
    'localhost' => 'http://localhost:8000',
    'private network' => 'http://192.168.1.10',
]);

it('clears the website when it is removed', function () {
    $business = Business::factory()->create(['url' => 'https://northwindcoffee.com']);

    $this->actingAs($business->user)
        ->put(route('businesses.update', $business), [
            'name' => $business->name,
            'url' => '',
            'description' => $business->description,
        ])
        ->assertSessionHasNoErrors();

    expect($business->fresh()->url)->toBeNull();
});

it('requires a name and a description', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('businesses.store'), ['name' => '', 'description' => ''])
        ->assertSessionHasErrors(['name', 'description']);
});

it('refuses a description that is too long to read on every creep', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('businesses.store'), [
            'name' => 'Northwind Coffee',
            'description' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors('description');
});

it('refuses a second business with the same name, but not somebody else\'s', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create(['name' => 'Northwind Coffee']);
    Business::factory()->create(['name' => 'Acme']);

    $this->actingAs($user)
        ->post(route('businesses.store'), ['name' => 'Northwind Coffee', 'description' => 'Again.'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('businesses.store'), ['name' => 'Acme', 'description' => 'Mine too.'])
        ->assertSessionHasNoErrors();
});

it('shows a business to its owner', function () {
    $business = Business::factory()->create();

    $this->actingAs($business->user)
        ->get(route('businesses.show', $business))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('businesses/show')
            ->where('business.data.id', $business->id)
            ->where('business.data.description', $business->description)
            ->where('business.data.url', $business->url)
        );
});

it('updates a business, keeping its own name', function () {
    $business = Business::factory()->create(['name' => 'Northwind Coffee']);

    $this->actingAs($business->user)
        ->put(route('businesses.update', $business), [
            'name' => 'Northwind Coffee',
            'description' => 'Now also selling grinders.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('businesses.show', $business));

    expect($business->fresh()->description)->toBe('Now also selling grinders.');
});

it('deletes a business', function () {
    $business = Business::factory()->create();

    $this->actingAs($business->user)
        ->delete(route('businesses.destroy', $business))
        ->assertRedirect(route('businesses.index'));

    expect($business->fresh())->toBeNull();
});

it('keeps other people out of a business', function () {
    $business = Business::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('businesses.show', $business))->assertForbidden();
    $this->actingAs($stranger)
        ->put(route('businesses.update', $business), ['name' => 'Mine now', 'description' => 'Mine now.'])
        ->assertForbidden();
    $this->actingAs($stranger)->delete(route('businesses.destroy', $business))->assertForbidden();

    expect($business->fresh()->name)->not->toBe('Mine now');
});

it('goes when its owner does', function () {
    $business = Business::factory()->create();

    $business->user->delete();

    expect($business->fresh())->toBeNull();
});
