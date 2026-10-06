<?php

use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Enums\PageStatus;
use App\Jobs\RunCreep;
use App\Models\ApiKey;
use App\Models\Competitor;
use App\Models\WatchedPage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('creates a page and starts creeping it immediately', function () {
    Queue::fake();

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();
    $key = ApiKey::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/kettle',
            'name' => 'Kettle',
            'api_key_id' => $key->id,
            'frequency' => CreepFrequency::Daily->value,
            'notify_on_change' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $watchedPage = $competitor->watchedPages()->sole();

    expect($watchedPage->url)->toBe('https://example.com/products/kettle')
        ->and($watchedPage->api_key_id)->toBe($key->id)
        ->and($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->frequency)->toBe(CreepFrequency::Daily)
        ->and($watchedPage->next_creep_at)->not->toBeNull();

    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->watchedPage->is($watchedPage));
});

it('rejects a URL that is already watched on this competitor', function () {
    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();
    WatchedPage::factory()->forUser($user)->create(['url' => 'https://example.com/products/kettle']);

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/kettle',
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('url');

    expect($competitor->watchedPages()->count())->toBe(1);
});

it('lets two users watch the same URL', function () {
    $url = 'https://example.com/products/kettle';
    WatchedPage::factory()->create(['url' => $url]);

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => $url,
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    expect($competitor->watchedPages()->count())->toBe(1);
});

it('will not show another user\'s page', function () {
    $watchedPage = WatchedPage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('watched-pages.show', $watchedPage))
        ->assertForbidden();
});

it('will not let another user change or delete a page', function () {
    $watchedPage = WatchedPage::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => CreepFrequency::Daily->value,
            'status' => PageStatus::Paused->value,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('watched-pages.destroy', $watchedPage))
        ->assertForbidden();

    expect($watchedPage->fresh())->not->toBeNull();
});

it('clears the next creep when a page is paused', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'name' => $watchedPage->name,
            'frequency' => $watchedPage->frequency->value,
            'notify_on_change' => false,
            'status' => PageStatus::Paused->value,
        ])
        ->assertSessionHasNoErrors();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Paused)
        ->and($watchedPage->next_creep_at)->toBeNull()
        ->and($watchedPage->notify_on_change)->toBeFalse();
});

it('reschedules a page that is un-paused', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->paused()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => CreepFrequency::Daily->value,
            'status' => PageStatus::Active->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->next_creep_at)->not->toBeNull();
});

it('deletes a page and everything found about it', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->delete(route('watched-pages.destroy', $watchedPage))
        ->assertRedirect(route('competitors.show', $watchedPage->competitor_id));

    expect(WatchedPage::query()->count())->toBe(0);
});

it('requires signing in', function () {
    $this->get(route('watched-pages.show', WatchedPage::factory()->create()))->assertRedirect(route('login'));
});

it('turns notifications off when the box is left unticked', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create(['notify_on_change' => true]);

    // A browser sends nothing at all for an unchecked box.
    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => $watchedPage->frequency->value,
            'status' => $watchedPage->status->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->notify_on_change)->toBeFalse();
});

it('turns notifications on when the box is ticked', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create(['notify_on_change' => false]);

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => $watchedPage->frequency->value,
            'status' => $watchedPage->status->value,
            'notify_on_change' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->notify_on_change)->toBeTrue();
});

it('defaults a new page to no notifications when the box is unticked', function () {
    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/quiet',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => 'daily',
        ])
        ->assertSessionHasNoErrors();

    expect($competitor->watchedPages()->sole()->notify_on_change)->toBeFalse();
});

it('saves what the user asked Creeper to watch for', function () {
    Queue::fake();

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'url' => 'https://example.com/changelog',
            'watch_for' => 'New releases: the version and the headline features.',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    expect($competitor->watchedPages()->sole()->watch_for)->toBe('New releases: the version and the headline features.');
});

it('offers no fixed kinds of page on the new-page form', function () {
    $competitor = Competitor::factory()->forUser($user = User::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('competitors.watched-pages.create', $competitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('watched-pages/create')
            ->where('competitor.data.id', $competitor->id)
            ->missing('types')
        );
});

it('needs to be told what to watch for', function () {
    Queue::fake();

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'url' => 'https://example.com/pricing',
            'watch_for' => '',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('watch_for');

    expect($competitor->watchedPages()->count())->toBe(0);
});

it('lets a page change what it watches for, and leaves it alone when not asked', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'watch_for' => 'Only the Pro plan.',
            'frequency' => $watchedPage->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->watch_for)->toBe('Only the Pro plan.');

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => $watchedPage->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->watch_for)->toBe('Only the Pro plan.');
});

it('will not start creeping without a key to pay for it', function () {
    Queue::fake();

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/kettle',
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('api_key_id');

    expect($competitor->watchedPages()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('will not spend somebody else\'s key', function () {
    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();
    $theirs = ApiKey::factory()->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/kettle',
            'api_key_id' => $theirs->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('api_key_id');

    expect($competitor->watchedPages()->count())->toBe(0);
});

it('offers the user their keys on the new-page form', function () {
    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();
    ApiKey::factory()->for($user)->create(['name' => 'Personal']);
    ApiKey::factory()->create(['name' => 'Somebody else\'s']);

    $this->actingAs($user)
        ->get(route('competitors.watched-pages.create', $competitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('apiKeys', 1)
            ->where('apiKeys.0.label', fn (string $label): bool => str_contains($label, 'Personal'))
        );
});

it('moves a page onto another key', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();
    $other = ApiKey::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'api_key_id' => $other->id,
            'frequency' => $watchedPage->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->api_key_id)->toBe($other->id);
});

it('leaves a page on its key when the form does not mention one', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'frequency' => $watchedPage->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->api_key_id)->toBe($watchedPage->api_key_id);
});

it('shows a page the keys it could be moved onto, and the one it is on', function () {
    $user = User::factory()->create();
    $key = ApiKey::factory()->for($user)->create(['name' => 'Personal']);
    $watchedPage = WatchedPage::factory()->forUser($user)->for($key)->create();
    ApiKey::factory()->create(['name' => 'Somebody else\'s']);

    $this->actingAs($user)
        ->get(route('watched-pages.show', $watchedPage))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('watchedPage.data.api_key_id', (string) $key->id)
            ->where('watchedPage.data.api_key_label', fn (string $label): bool => str_contains($label, 'Personal'))
            ->has('apiKeys', 1)
        );
});

it('lets two competitors of one business watch the same URL', function () {
    Queue::fake();

    $user = User::factory()->create();
    $url = 'https://example.com/products/kettle';
    WatchedPage::factory()->forUser($user)->create(['url' => $url]);
    $second = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $second), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => $url,
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    expect($second->watchedPages()->count())->toBe(1);
});

it('will not watch a page on somebody else\'s competitor', function () {
    Queue::fake();

    $theirs = Competitor::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $theirs), [
            'category' => 'pricing',
            'watch_for' => 'Each plan\'s name and price.',
            'url' => 'https://example.com/products/kettle',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('competitors.watched-pages.create', $theirs))
        ->assertForbidden();

    expect($theirs->watchedPages()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('files a page under the kind of page it is, and lets that change', function () {
    Queue::fake();

    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'url' => 'https://example.com/changelog',
            'watch_for' => 'New releases.',
            'category' => 'releases',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    $watchedPage = $competitor->watchedPages()->sole();

    expect($watchedPage->category)->toBe(PageCategory::Releases);

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'category' => 'messaging',
            'frequency' => $watchedPage->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    expect($watchedPage->fresh()->category)->toBe(PageCategory::Messaging);
});

it('needs to know what kind of page it is, and only knows a few', function () {
    $user = User::factory()->create();
    $competitor = Competitor::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->post(route('competitors.watched-pages.store', $competitor), [
            'url' => 'https://example.com/forecast',
            'watch_for' => 'The weather.',
            'category' => 'weather',
            'api_key_id' => ApiKey::factory()->for($user)->create()->id,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('category');
});
