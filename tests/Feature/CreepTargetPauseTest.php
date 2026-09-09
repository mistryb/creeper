<?php

use App\Enums\CreepFrequency;
use App\Enums\TargetStatus;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('pauses a target without touching anything it found', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create(['frequency' => CreepFrequency::Hourly]);
    ProductSnapshot::factory()->forTarget($target)->count(3)->create();

    $this->actingAs($user)
        ->post(route('creep-targets.pause.store', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Paused)
        ->and($target->next_creep_at)->toBeNull()
        ->and($target->snapshots()->count())->toBe(3);
});

it('leaves a paused target out of the scheduler sweep', function () {
    Queue::fake();

    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->due()->create();

    $this->actingAs($user)->post(route('creep-targets.pause.store', $target));

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertNotPushed(RunCreep::class);
});

it('refuses to creep a paused target on demand', function () {
    Queue::fake();

    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->paused()->create();

    $this->actingAs($user)
        ->post(route('creep-targets.runs.store', $target))
        ->assertRedirect();

    Queue::assertNotPushed(RunCreep::class);
    expect($target->fresh()->status)->toBe(TargetStatus::Paused);
});

it('resumes a paused target back onto its schedule', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->paused()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->delete(route('creep-targets.pause.destroy', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Active)
        ->and($target->next_creep_at)->not->toBeNull();
});

it('resumes a parked target and clears its failure streak', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->failing()->create(['frequency' => CreepFrequency::Daily]);

    $this->actingAs($user)
        ->delete(route('creep-targets.pause.destroy', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Active)
        ->and($target->consecutive_failures)->toBe(0)
        ->and($target->next_creep_at)->not->toBeNull();
});

it('resumes a manual target without scheduling it', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->manual()->paused()->create();

    $this->actingAs($user)
        ->delete(route('creep-targets.pause.destroy', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Active)
        ->and($target->next_creep_at)->toBeNull();
});

it('will not let another user pause or resume a target', function () {
    $target = CreepTarget::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('creep-targets.pause.store', $target))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('creep-targets.pause.destroy', $target))
        ->assertForbidden();

    expect($target->fresh()->status)->toBe(TargetStatus::Active);
});

it('leaves the status alone when the settings form omits it', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->paused()->create();

    $this->actingAs($user)
        ->put(route('creep-targets.update', $target), [
            'url' => $target->url,
            'name' => 'Renamed while paused',
            'frequency' => CreepFrequency::Weekly->value,
            'notify_on_change' => true,
        ])
        ->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Paused)
        ->and($target->name)->toBe('Renamed while paused')
        ->and($target->next_creep_at)->toBeNull();
});
