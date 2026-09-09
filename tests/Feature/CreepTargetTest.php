<?php

use App\Enums\CreepFrequency;
use App\Enums\CreepType;
use App\Enums\TargetStatus;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('lists only the signed-in user\'s targets', function () {
    $user = User::factory()->create();
    $mine = CreepTarget::factory()->for($user)->create(['name' => 'My kettle']);
    $theirs = CreepTarget::factory()->create(['name' => 'Somebody else\'s kettle']);

    $this->actingAs($user)
        ->get(route('creep-targets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('creep-targets/index')
            ->has('targets.data', 1)
            ->where('targets.data.0.id', $mine->id)
        );

    expect($theirs->fresh())->not->toBeNull();
});

it('creates a target and starts creeping it immediately', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => CreepType::Product->value,
            'url' => 'https://example.com/products/kettle',
            'name' => 'Kettle',
            'frequency' => CreepFrequency::Daily->value,
            'notify_on_change' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $target = $user->creepTargets()->sole();

    expect($target->url)->toBe('https://example.com/products/kettle')
        ->and($target->status)->toBe(TargetStatus::Active)
        ->and($target->frequency)->toBe(CreepFrequency::Daily)
        ->and($target->next_creep_at)->not->toBeNull();

    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->target->is($target));
});

it('rejects a URL that is already being crept by this user', function () {
    $user = User::factory()->create();
    CreepTarget::factory()->for($user)->create(['url' => 'https://example.com/products/kettle']);

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => CreepType::Product->value,
            'url' => 'https://example.com/products/kettle',
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('url');

    expect($user->creepTargets()->count())->toBe(1);
});

it('lets two users creep the same URL', function () {
    $url = 'https://example.com/products/kettle';
    CreepTarget::factory()->create(['url' => $url]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => CreepType::Product->value,
            'url' => $url,
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->creepTargets()->count())->toBe(1);
});

it('will not show another user\'s target', function () {
    $target = CreepTarget::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('creep-targets.show', $target))
        ->assertForbidden();
});

it('will not let another user change or delete a target', function () {
    $target = CreepTarget::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'frequency' => CreepFrequency::Daily->value,
            'status' => TargetStatus::Paused->value,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('creep-targets.destroy', $target))
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull();
});

it('clears the next creep when a target is paused', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'name' => $target->name,
            'frequency' => $target->frequency->value,
            'notify_on_change' => false,
            'status' => TargetStatus::Paused->value,
        ])
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Paused)
        ->and($target->next_creep_at)->toBeNull()
        ->and($target->notify_on_change)->toBeFalse();
});

it('reschedules a target that is un-paused', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->paused()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'frequency' => CreepFrequency::Daily->value,
            'status' => TargetStatus::Active->value,
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->next_creep_at)->not->toBeNull();
});

it('deletes a target and everything found about it', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('creep-targets.destroy', $target))
        ->assertRedirect(route('creep-targets.index'));

    expect(CreepTarget::query()->count())->toBe(0);
});

it('requires signing in', function () {
    $this->get(route('creep-targets.index'))->assertRedirect(route('login'));
});

it('turns notifications off when the box is left unticked', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create(['notify_on_change' => true]);

    // A browser sends nothing at all for an unchecked box.
    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'frequency' => $target->frequency->value,
            'status' => $target->status->value,
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->notify_on_change)->toBeFalse();
});

it('turns notifications on when the box is ticked', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create(['notify_on_change' => false]);

    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'frequency' => $target->frequency->value,
            'status' => $target->status->value,
            'notify_on_change' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->notify_on_change)->toBeTrue();
});

it('defaults a new target to no notifications when the box is unticked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => CreepType::Product->value,
            'url' => 'https://example.com/products/quiet',
            'frequency' => 'daily',
        ])
        ->assertSessionHasNoErrors();

    expect($user->creepTargets()->sole()->notify_on_change)->toBeFalse();
});

it('lets the user pick what kind of creeping to do', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => CreepType::Changelog->value,
            'url' => 'https://example.com/changelog',
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->creepTargets()->sole()->type)->toBe(CreepType::Changelog);
});

it('offers every kind of creeping on the new-target form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('creep-targets.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('creep-targets/create')
            ->has('types', count(CreepType::cases()))
            ->where('types.0.value', CreepType::Product->value)
        );
});

it('refuses a kind of creeping it does not do', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.store'), [
            'type' => 'weather',
            'url' => 'https://example.com/forecast',
            'frequency' => CreepFrequency::Daily->value,
        ])
        ->assertSessionHasErrors('type');

    expect($user->creepTargets()->count())->toBe(0);
});

it('will not let a target change what kind of creeping it is', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'type' => CreepType::Changelog->value,
            'url' => $target->url,
            'frequency' => $target->frequency->value,
        ])
        ->assertSessionHasNoErrors();

    // Readings are filed in a table per type, so a target that switched would
    // strand everything already found.
    expect($target->fresh()->type)->toBe(CreepType::Product);
});
