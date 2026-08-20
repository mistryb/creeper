<?php

use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;

it('counts only the signed-in user\'s work', function () {
    $user = User::factory()->create();

    CreepTarget::factory()->count(2)->for($user)->create();
    CreepTarget::factory()->for($user)->failing()->create();
    CreepTarget::factory()->count(4)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.targets', 3)
            ->where('stats.active', 2)
            ->where('stats.failing', 1)
        );
});

it('shows recent changes and runs with their targets attached', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create(['name' => 'Kettle']);

    CreepChange::factory()->create(['creep_target_id' => $target->id]);
    CreepRun::factory()->create(['creep_target_id' => $target->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('recentChanges.data', 1)
            ->where('recentChanges.data.0.target.display_name', 'Kettle')
            ->has('recentRuns.data', 1)
            ->has('watchlist.data', 1)
        );
});

it('counts changes only from the last week', function () {
    $user = User::factory()->create();
    $target = CreepTarget::factory()->for($user)->create();

    CreepChange::factory()->create([
        'creep_target_id' => $target->id,
        'detected_at' => now()->subDays(2),
    ]);
    CreepChange::factory()->create([
        'creep_target_id' => $target->id,
        'detected_at' => now()->subMonth(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stats.changesThisWeek', 1));
});
