<?php

use App\Billing\RunMeter;
use App\Creeping\CreepManager;
use App\Creeping\Drivers\FakeCreepDriver;
use App\Enums\RunStatus;
use App\Jobs\ReportCreepRunUsage;
use App\Jobs\RunCreep;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

function meter(): RunMeter
{
    return app(RunMeter::class);
}

function subscribedUserWithKey(): User
{
    $user = User::factory()->create();
    subscribe($user);
    $user->setCreepApiKey('sk-ant-test-key-0000000000');

    return $user->refresh();
}

it('counts only this account\'s successful runs from this month', function () {
    $user = subscribedUserWithKey();
    $target = CreepTarget::factory()->for($user)->create();

    CreepRun::factory()->count(3)->for($target, 'target')->create();
    CreepRun::factory()->for($target, 'target')->failed()->create();
    CreepRun::factory()->for($target, 'target')->create(['created_at' => now()->subMonth()]);

    // Somebody else's runs are somebody else's bill.
    CreepRun::factory()->count(5)->create();

    expect(meter()->runsThisMonth($user))->toBe(3);
});

it('knows what the allowance covers and what spills past it', function () {
    $user = subscribedUserWithKey();
    $target = CreepTarget::factory()->for($user)->create();

    config(['billing.plans.creeper.included_runs' => 4]);

    CreepRun::factory()->count(6)->for($target, 'target')->create();

    expect(meter()->includedRuns($user))->toBe(4)
        ->and(meter()->runsRemaining($user))->toBe(0)
        ->and(meter()->billableOverage($user))->toBe(2)
        ->and(meter()->overageAmount($user))->toBe(2);
});

it('has no allowance to speak of when billing is off', function () {
    config(['billing.enabled' => false]);

    $user = User::factory()->create();

    expect(meter()->includedRuns($user))->toBeNull()
        ->and(meter()->runsRemaining($user))->toBeNull()
        ->and(meter()->ceiling())->toBeNull()
        ->and(meter()->allowsRun($user))->toBeTrue();
});

it('reports a completed run to the meter', function () {
    // Only the usage job is faked — faking the whole queue would swallow the
    // synchronous creep as well, and then there would be nothing to report.
    Queue::fake([ReportCreepRunUsage::class]);

    $user = subscribedUserWithKey();
    $target = CreepTarget::factory()->for($user)->create();

    config(['creeping.driver' => 'fake']);

    /** @var FakeCreepDriver $driver */
    $driver = app(CreepManager::class)->driver('fake');
    $driver->willReturn(['title' => 'Kettle', 'price' => '£24.99']);

    RunCreep::dispatchSync($target);

    Queue::assertPushed(
        ReportCreepRunUsage::class,
        fn (ReportCreepRunUsage $job): bool => $job->run->target->is($target),
    );
});

it('does not report usage for an account with no subscription', function () {
    config(['billing.enabled' => true, 'stripe.secret' => 'sk_test_invalid']);

    $run = CreepRun::factory()->create();

    // A Stripe call here would blow up on the invalid key, so getting through
    // this cleanly is the assertion.
    meter()->report($run);
})->throwsNoExceptions();

it('refuses to creep once the run ceiling is reached', function () {
    $user = subscribedUserWithKey();
    $target = CreepTarget::factory()->for($user)->create();

    config(['billing.run_ceiling' => 2]);

    CreepRun::factory()->count(2)->for($target, 'target')->create();

    RunCreep::dispatchSync($target);

    $refused = $target->runs()->latest('id')->first();

    expect($refused->status)->toBe(RunStatus::Failed)
        ->and($refused->error)->toContain('run ceiling')
        ->and($target->refresh()->consecutive_failures)->toBe(0);
});

it('refuses to creep for an account with no subscription', function () {
    config(['billing.enabled' => true]);

    $target = CreepTarget::factory()->create();

    RunCreep::dispatchSync($target);

    expect($target->runs()->sole()->error)->toContain('no active subscription');
});

it('creeps freely when the install is self-hosted', function () {
    config(['billing.enabled' => false, 'creeping.driver' => 'fake']);

    $target = CreepTarget::factory()->create();

    /** @var FakeCreepDriver $driver */
    $driver = app(CreepManager::class)->driver('fake');
    $driver->willReturn(['title' => 'Kettle', 'price' => '£24.99']);

    RunCreep::dispatchSync($target);

    expect($target->runs()->sole()->status)->toBe(RunStatus::Succeeded);
});
