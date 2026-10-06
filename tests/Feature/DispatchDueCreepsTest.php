<?php

use App\Enums\CreepFrequency;
use App\Jobs\RunCreep;
use App\Models\WatchedPage;
use Illuminate\Support\Facades\Queue;

it('dispatches only pages that are due', function () {
    Queue::fake();

    $due = WatchedPage::factory()->due()->create();
    $notYet = WatchedPage::factory()->create([
        'frequency' => CreepFrequency::Hourly,
        'next_creep_at' => now()->addHour(),
    ]);
    $paused = WatchedPage::factory()->paused()->create();
    $manual = WatchedPage::factory()->manual()->create();

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertPushed(RunCreep::class, 1);
    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->watchedPage->is($due));

    foreach ([$notYet, $paused, $manual] as $watchedPage) {
        Queue::assertNotPushed(RunCreep::class, fn (RunCreep $job): bool => $job->watchedPage->is($watchedPage));
    }
});

it('skips a page whose status is failed even if it looks due', function () {
    Queue::fake();

    WatchedPage::factory()->failing()->create(['next_creep_at' => now()->subHour()]);

    $this->artisan('creeper:dispatch-due')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('says nothing was due when nothing was', function () {
    Queue::fake();

    $this->artisan('creeper:dispatch-due')
        ->expectsOutputToContain('Dispatched 0 creeps.')
        ->assertSuccessful();
});
