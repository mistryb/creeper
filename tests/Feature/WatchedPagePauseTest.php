<?php

use App\Enums\CreepFrequency;
use App\Enums\PageStatus;
use App\Jobs\RunCreep;
use App\Models\WatchedPage;
use App\Models\PageSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('pauses a page without touching anything it found', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create(['frequency' => CreepFrequency::Hourly]);
    PageSnapshot::factory()->of($watchedPage)->count(3)->create();

    $this->actingAs($user)
        ->post(route('watched-pages.pause.store', $watchedPage))
        ->assertRedirect();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Paused)
        ->and($watchedPage->next_creep_at)->toBeNull()
        ->and($watchedPage->snapshots()->count())->toBe(3);
});

it('leaves a paused page out of the scheduler sweep', function () {
    Queue::fake();

    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->due()->create();

    $this->actingAs($user)->post(route('watched-pages.pause.store', $watchedPage));

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertNotPushed(RunCreep::class);
});

it('refuses to creep a paused page on demand', function () {
    Queue::fake();

    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->paused()->create();

    $this->actingAs($user)
        ->post(route('watched-pages.runs.store', $watchedPage))
        ->assertRedirect();

    Queue::assertNotPushed(RunCreep::class);
    expect($watchedPage->fresh()->status)->toBe(PageStatus::Paused);
});

it('resumes a paused page back onto its schedule', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->paused()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->delete(route('watched-pages.pause.destroy', $watchedPage))
        ->assertRedirect();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->next_creep_at)->not->toBeNull();
});

it('resumes a parked page and clears its failure streak', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->failing()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->delete(route('watched-pages.pause.destroy', $watchedPage))
        ->assertRedirect();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->consecutive_failures)->toBe(0)
        ->and($watchedPage->next_creep_at)->not->toBeNull();
});

it('resumes a manual page without scheduling it', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->manual()->paused()->create();

    $this->actingAs($user)
        ->delete(route('watched-pages.pause.destroy', $watchedPage))
        ->assertRedirect();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->next_creep_at)->toBeNull();
});

it('will not let another user pause or resume a page', function () {
    $watchedPage = WatchedPage::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('watched-pages.pause.store', $watchedPage))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('watched-pages.pause.destroy', $watchedPage))
        ->assertForbidden();

    expect($watchedPage->fresh()->status)->toBe(PageStatus::Active);
});

it('leaves the status alone when the settings form omits it', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->paused()->create();

    $this->actingAs($user)
        ->put(route('watched-pages.update', $watchedPage), [
            'url' => $watchedPage->url,
            'name' => 'Renamed while paused',
            'frequency' => CreepFrequency::Weekly->value,
            'notify_on_change' => true,
        ])
        ->assertSessionHasNoErrors();

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Paused)
        ->and($watchedPage->name)->toBe('Renamed while paused')
        ->and($watchedPage->next_creep_at)->toBeNull();
});
