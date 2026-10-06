<?php

use App\Models\Business;
use App\Models\User;

it('shares no businesses with an account that has none', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('businessChooser.current', null)
            ->where('businessChooser.all', [])
        );
});

it('falls back to the first business when none has been picked', function () {
    $user = User::factory()->create();
    $first = Business::factory()->for($user)->create(['name' => 'Northwind Coffee']);
    Business::factory()->for($user)->create(['name' => 'Northwind Tea']);
    Business::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('businessChooser.current', ['id' => $first->id, 'name' => 'Northwind Coffee'])
            ->has('businessChooser.all', 2)
        );
});

it('switches business and goes to it', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create();
    $second = Business::factory()->for($user)->create(['name' => 'Northwind Tea']);

    $this->actingAs($user)
        ->put(route('current-business.update'), ['business_id' => $second->id])
        ->assertRedirect(route('businesses.show', $second));

    expect($user->fresh()->current_business_id)->toBe($second->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('businessChooser.current.id', $second->id));
});

it('will not switch to somebody else\'s business', function () {
    $user = User::factory()->create();
    $theirs = Business::factory()->create();

    $this->actingAs($user)
        ->put(route('current-business.update'), ['business_id' => $theirs->id])
        ->assertSessionHasErrors('business_id');

    expect($user->fresh()->current_business_id)->toBeNull();
});

it('makes a newly set-up business the current one', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create();

    $this->actingAs($user)->post(route('businesses.store'), [
        'name' => 'Northwind Tea',
        'description' => 'Loose-leaf tea.',
    ]);

    expect($user->fresh()->currentBusiness->name)->toBe('Northwind Tea');
});

it('falls back to another business when the current one is deleted', function () {
    $user = User::factory()->create();
    $first = Business::factory()->for($user)->create();
    $second = Business::factory()->for($user)->create();
    $user->switchBusiness($second);

    $this->actingAs($user)->delete(route('businesses.destroy', $second));

    expect($user->fresh()->current_business_id)->toBeNull()
        ->and($user->fresh()->selectedBusiness()->is($first))->toBeTrue();
});

it('keeps the chooser intact on the businesses page', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create();

    // A page prop with the same name as the shared one would replace it, and
    // the sidebar would crash on the one page that lists businesses.
    $this->actingAs($user)
        ->get(route('businesses.index'))
        ->assertInertia(fn ($page) => $page
            ->has('businesses.data', 1)
            ->has('businessChooser.all', 1)
        );
});
