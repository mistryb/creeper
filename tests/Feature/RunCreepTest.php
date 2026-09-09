<?php

use App\Enums\Availability;
use App\Enums\RunStatus;
use App\Enums\TargetStatus;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use App\Notifications\TargetChanged;
use Illuminate\Support\Facades\Notification;

it('records a run and a snapshot', function () {
    $target = CreepTarget::factory()->create();

    fakeDriver()->willReturn([
        'title' => 'Stainless Kettle',
        'brand' => 'Acme',
        'price' => '£24.99',
        'availability' => 'in stock',
        'rating' => 4.5,
        'review_count' => 812,
    ]);

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();
    $snapshot = $target->snapshots()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('fake')
        ->and($run->finished_at)->not->toBeNull()
        ->and($snapshot->title)->toBe('Stainless Kettle')
        ->and($snapshot->price_amount)->toBe(2499)
        ->and($snapshot->currency)->toBe('GBP')
        ->and($snapshot->availability)->toBe(Availability::InStock)
        ->and($snapshot->review_count)->toBe(812);
});

it('moves the schedule on after a successful run', function () {
    $target = CreepTarget::factory()->due()->create();

    fakeDriver();

    RunCreep::dispatchSync($target);

    $target->refresh();

    expect($target->last_crept_at)->not->toBeNull()
        ->and($target->next_creep_at)->not->toBeNull()
        ->and($target->next_creep_at->isFuture())->toBeTrue();
});

it('records a change when the price moves', function () {
    Notification::fake();

    $target = CreepTarget::factory()->create();

    $driver = fakeDriver();
    $driver->willReturn(['title' => 'Kettle', 'price_amount' => 2499, 'currency' => 'GBP']);
    RunCreep::dispatchSync($target);

    $driver->willReturn(['title' => 'Kettle', 'price_amount' => 1999, 'currency' => 'GBP']);
    RunCreep::dispatchSync($target);

    $change = $target->changes()->sole();

    expect($change->field)->toBe('price')
        ->and($change->direction->value)->toBe('down')
        ->and($target->snapshots()->count())->toBe(2);

    Notification::assertSentTo($target->user, TargetChanged::class);
});

it('records nothing when nothing moved', function () {
    Notification::fake();

    $target = CreepTarget::factory()->create();

    $driver = fakeDriver();
    $payload = ['title' => 'Kettle', 'price_amount' => 2499, 'currency' => 'GBP'];

    $driver->willReturn($payload);
    RunCreep::dispatchSync($target);

    $driver->willReturn($payload);
    RunCreep::dispatchSync($target);

    expect($target->changes()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('stays quiet when the target has notifications turned off', function () {
    Notification::fake();

    $target = CreepTarget::factory()->create(['notify_on_change' => false]);

    $driver = fakeDriver();
    $driver->willReturn(['title' => 'Kettle', 'price_amount' => 2499, 'currency' => 'GBP']);
    RunCreep::dispatchSync($target);

    $driver->willReturn(['title' => 'Kettle', 'price_amount' => 1999, 'currency' => 'GBP']);
    RunCreep::dispatchSync($target);

    expect($target->changes()->count())->toBe(1);

    Notification::assertNothingSent();
});

it('marks the run failed when the driver reports a failure', function () {
    $target = CreepTarget::factory()->create();

    fakeDriver()->willFail('That page is not a product.');

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();
    $target->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('That page is not a product.')
        ->and($target->consecutive_failures)->toBe(1)
        ->and($target->status)->toBe(TargetStatus::Active)
        ->and($target->snapshots()->count())->toBe(0);
});

it('parks a target that keeps failing', function () {
    $target = CreepTarget::factory()->create();

    fakeDriver()->willFail();

    foreach (range(1, CreepTarget::FAILURE_LIMIT) as $ignored) {
        RunCreep::dispatchSync($target);
    }

    $target->refresh();

    expect($target->consecutive_failures)->toBe(CreepTarget::FAILURE_LIMIT)
        ->and($target->status)->toBe(TargetStatus::Failed)
        ->and($target->next_creep_at)->toBeNull();
});

it('revives a parked target when it succeeds again', function () {
    $target = CreepTarget::factory()->failing()->create();

    fakeDriver()->willReturn(['title' => 'Back from the dead', 'price_amount' => 100]);

    RunCreep::dispatchSync($target);

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Active)
        ->and($target->consecutive_failures)->toBe(0);
});

it('fails the run when the payload has nothing usable in it', function () {
    $target = CreepTarget::factory()->create();

    fakeDriver()->willReturn(['brand' => 'Acme']);

    RunCreep::dispatchSync($target);

    expect($target->runs()->sole()->status)->toBe(RunStatus::Failed)
        ->and($target->snapshots()->count())->toBe(0);
});
