<?php

use App\Enums\DigestFrequency;
use App\Jobs\RunCompetitorDiscovery;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * A complete set-up form for a user, with a key of theirs to spend.
 *
 * @return array<string, mixed>
 */
function companyForm(User $user, array $overrides = []): array
{
    Queue::fake();

    return [
        'name' => 'Northwind Coffee',
        'description' => 'Small-batch roaster selling subscriptions to home brewers.',
        'digest_frequency' => 'weekly',
        'api_key_id' => ApiKey::factory()->for($user)->create()->id,
        ...$overrides,
    ];
}

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
        ->post(route('businesses.store'), companyForm($user))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $business = $user->businesses()->sole();

    expect($business->name)->toBe('Northwind Coffee')
        ->and($business->description)->toBe('Small-batch roaster selling subscriptions to home brewers.')
        ->and($business->digest_frequency)->toBe(DigestFrequency::Weekly)
        ->and($business->api_key_id)->not->toBeNull()
        ->and($user->fresh()->current_business_id)->toBe($business->id);
});

it('goes looking for competitors as soon as a company is set up, on its key', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $user = User::factory()->create();
    $form = companyForm($user, ['digest_frequency' => 'daily']);

    $this->actingAs($user)->post(route('businesses.store'), $form)->assertSessionHasNoErrors();

    $business = $user->businesses()->sole();
    $discovery = $business->discoveries()->sole();

    expect($discovery->api_key_id)->toBe($form['api_key_id'])
        // Tomorrow morning.
        ->and($business->next_digest_at->toDateTimeString())->toBe('2026-10-15 08:00:00');

    Queue::assertPushed(RunCompetitorDiscovery::class, fn (RunCompetitorDiscovery $job): bool => $job->discovery->is($discovery));
});

it('insists on a cadence and one of the user\'s own keys', function () {
    $user = User::factory()->create();
    $theirs = ApiKey::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['digest_frequency' => 'hourly', 'api_key_id' => $theirs->id]))
        ->assertSessionHasErrors(['digest_frequency', 'api_key_id']);

    $this->actingAs($user)
        ->post(route('businesses.store'), ['name' => 'Northwind', 'description' => 'Coffee.'])
        ->assertSessionHasErrors(['digest_frequency', 'api_key_id']);
});

it('lets one user run several businesses', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create(['name' => 'Northwind Coffee']);

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['name' => 'Northwind Tea', 'description' => 'Loose-leaf tea, same shop.']))
        ->assertSessionHasNoErrors();

    expect($user->businesses()->count())->toBe(2);
});

it('saves the business\'s website', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['url' => 'https://northwindcoffee.com']))
        ->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->url)->toBe('https://northwindcoffee.com');
});

it('treats the website as optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['url' => '']))
        ->assertSessionHasNoErrors();

    expect($user->businesses()->sole()->url)->toBeNull();
});

it('refuses a website that is not a public URL', function (string $url) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['url' => $url]))
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
            'digest_frequency' => 'weekly',
        ])
        ->assertSessionHasNoErrors();

    expect($business->fresh()->url)->toBeNull();
});

it('requires a name and a description', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('businesses.store'), ['name' => '', 'description' => ''])
        ->assertSessionHasErrors(['name', 'description']);
});

it('refuses a description that is too long to read on every digest', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['description' => str_repeat('a', 5001)]))
        ->assertSessionHasErrors('description');
});

it('refuses a second business with the same name, but not somebody else\'s', function () {
    $user = User::factory()->create();
    Business::factory()->for($user)->create(['name' => 'Northwind Coffee']);
    Business::factory()->create(['name' => 'Acme']);

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['description' => 'Again.']))
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('businesses.store'), companyForm($user, ['name' => 'Acme', 'description' => 'Mine too.']))
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

it('updates a business, keeping its own name and its key when none is sent', function () {
    $business = Business::factory()->withKey()->create(['name' => 'Northwind Coffee']);

    $this->actingAs($business->user)
        ->put(route('businesses.update', $business), [
            'name' => 'Northwind Coffee',
            'description' => 'Now also selling grinders.',
            'digest_frequency' => 'weekly',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('businesses.show', $business));

    expect($business->fresh()->description)->toBe('Now also selling grinders.')
        ->and($business->fresh()->api_key_id)->toBe($business->api_key_id);
});

it('reschedules the next digest when the cadence changes, and not otherwise', function () {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $business = Business::factory()->create(['digest_frequency' => DigestFrequency::Weekly, 'next_digest_at' => Carbon::parse('2026-10-19 08:00:00')]);

    $form = ['name' => $business->name, 'description' => $business->description];

    $this->actingAs($business->user)
        ->put(route('businesses.update', $business), [...$form, 'digest_frequency' => 'weekly'])
        ->assertSessionHasNoErrors();

    expect($business->fresh()->next_digest_at->toDateTimeString())->toBe('2026-10-19 08:00:00');

    $this->actingAs($business->user)
        ->put(route('businesses.update', $business), [...$form, 'digest_frequency' => 'daily'])
        ->assertSessionHasNoErrors();

    expect($business->fresh()->digest_frequency)->toBe(DigestFrequency::Daily)
        ->and($business->fresh()->next_digest_at->toDateTimeString())->toBe('2026-10-15 08:00:00');
});

it('offers the cadences and the user\'s keys on the forms', function () {
    $business = Business::factory()->withKey()->create();

    $this->actingAs($business->user)
        ->get(route('businesses.create'))
        ->assertInertia(fn ($page) => $page
            ->has('frequencies', 2)
            ->has('apiKeys', 1)
            ->where('apiKeys.0.value', (string) $business->api_key_id)
        );

    $this->actingAs($business->user)
        ->get(route('businesses.show', $business))
        ->assertInertia(fn ($page) => $page
            ->where('business.data.api_key_id', (string) $business->api_key_id)
            ->has('apiKeys', 1)
        );
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
        ->put(route('businesses.update', $business), ['name' => 'Mine now', 'description' => 'Mine now.', 'digest_frequency' => 'weekly'])
        ->assertForbidden();
    $this->actingAs($stranger)->delete(route('businesses.destroy', $business))->assertForbidden();

    expect($business->fresh()->name)->not->toBe('Mine now');
});

it('goes when its owner does', function () {
    $business = Business::factory()->create();

    $business->user->delete();

    expect($business->fresh())->toBeNull();
});
