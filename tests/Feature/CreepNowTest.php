<?php

use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('creeps a target on demand', function () {
    Queue::fake();

    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('creep-targets.show', $target))
        ->post(route('creep-targets.runs.store', $target))
        ->assertRedirect(route('creep-targets.show', $target));

    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->target->is($target));
});

it('does not queue a second creep while one is in flight', function () {
    Queue::fake();

    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();
    CreepRun::factory()->running()->create(['creep_target_id' => $target->id]);

    $this->actingAs($user)
        ->from(route('creep-targets.show', $target))
        ->post(route('creep-targets.runs.store', $target))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

it('will not let anyone creep somebody else\'s target', function () {
    Queue::fake();

    $target = CreepTarget::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('creep-targets.runs.store', $target))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('shows a target with its history, runs and changes', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    $first = $target->snapshots()->create([
        'creep_run_id' => CreepRun::factory()->create(['creep_target_id' => $target->id])->id,
        'title' => 'Kettle',
        'price_amount' => 2499,
        'currency' => 'GBP',
        'captured_at' => now()->subDay(),
    ]);

    $second = $target->snapshots()->create([
        'creep_run_id' => CreepRun::factory()->create(['creep_target_id' => $target->id])->id,
        'title' => 'Kettle',
        'price_amount' => 1999,
        'currency' => 'GBP',
        'captured_at' => now(),
    ]);

    $target->changes()->create([
        'from_snapshot_id' => $first->id,
        'to_snapshot_id' => $second->id,
        'field' => 'price',
        'old_value' => '£24.99',
        'new_value' => '£19.99',
        'direction' => 'down',
        'detected_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('creep-targets.show', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('creep-targets/show')
            ->where('target.data.id', $target->id)
            ->where('target.data.latest_snapshot.price_amount', 1999)
            ->has('history.data', 2)
            ->has('runs.data', 2)
            ->has('changes.data', 1)
            ->where('isCreeping', false)
        );
});

it('reports a target that is mid-creep so the button can wait', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();
    CreepRun::factory()->running()->create(['creep_target_id' => $target->id]);

    $this->actingAs($user)
        ->get(route('creep-targets.show', $target))
        ->assertInertia(fn ($page) => $page->where('isCreeping', true));
});

it('offers every schedule on the new target form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('creep-targets.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('creep-targets/create')
            ->has('frequencies', 4)
        );
});

it('marks the run finished with a duration', function () {
    $run = CreepRun::factory()->running()->create(['started_at' => now()->subSeconds(2)]);

    $run->finish(RunStatus::Succeeded);

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->duration_ms)->toBeGreaterThanOrEqual(2000);
});
