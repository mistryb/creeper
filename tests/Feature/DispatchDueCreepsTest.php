<?php

use App\Enums\CreepFrequency;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use Illuminate\Support\Facades\Queue;

it('dispatches only targets that are due', function () {
    Queue::fake();

    $due = CreepTarget::factory()->due()->create();
    $notYet = CreepTarget::factory()->create([
        'frequency' => CreepFrequency::Hourly,
        'next_creep_at' => now()->addHour(),
    ]);
    $paused = CreepTarget::factory()->paused()->create();
    $manual = CreepTarget::factory()->manual()->create();

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertPushed(RunCreep::class, 1);
    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->target->is($due));

    foreach ([$notYet, $paused, $manual] as $target) {
        Queue::assertNotPushed(RunCreep::class, fn (RunCreep $job): bool => $job->target->is($target));
    }
});

it('skips a target whose status is failed even if it looks due', function () {
    Queue::fake();

    CreepTarget::factory()->failing()->create(['next_creep_at' => now()->subHour()]);

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('says nothing was due when nothing was', function () {
    Queue::fake();

    $this->artisan('creeper:dispatch-due')
        ->expectsOutputToContain('Dispatched 0 creeps.')
        ->assertSuccessful();
});
