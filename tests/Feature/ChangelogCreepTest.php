<?php

use App\Enums\CreepType;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\ChangelogSnapshot;
use App\Models\CreepTarget;
use App\Notifications\TargetChanged;
use Illuminate\Support\Facades\Notification;

/**
 * One release, shaped the way an agent reports it.
 *
 * @return array<string, mixed>
 */
function release(string $version, string $feature = 'Bulk export', string $kind = 'feature'): array
{
    return [
        'version' => $version,
        'released_on' => '2026-03-14',
        'title' => null,
        'summary' => 'The '.$version.' release.',
        'features' => [
            ['title' => $feature, 'description' => null, 'kind' => $kind],
        ],
    ];
}

it('files the releases a changelog lists', function () {
    $target = CreepTarget::factory()->changelog()->create();

    fakeDriver()->willReturn([
        'product' => 'Widgets',
        'latest_version' => 'v2.1.0',
        'releases' => [release('v2.1.0', 'Bulk export'), release('v2.0.0', 'Single sign-on')],
    ]);

    RunCreep::dispatchSync($target);

    $run = $target->runs()->sole();
    $snapshot = $target->changelogSnapshots()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($snapshot->product)->toBe('Widgets')
        ->and($snapshot->latest_version)->toBe('v2.1.0')
        ->and($snapshot->latest_released_on->toDateString())->toBe('2026-03-14')
        ->and($snapshot->release_count)->toBe(2)
        ->and($snapshot->feature_count)->toBe(2)
        ->and($snapshot->releases[0]['version'])->toBe('v2.1.0')
        ->and($snapshot->releases[0]['features'][0]['title'])->toBe('Bulk export')
        ->and($snapshot->releases[0]['features'][0]['kind'])->toBe('feature')
        // A changelog target never touches the product table.
        ->and($target->snapshots()->count())->toBe(0);
});

it('reports a release that was not on the page last time', function () {
    Notification::fake();

    $target = CreepTarget::factory()->changelog()->create();
    $driver = fakeDriver();

    $driver->willReturn(['releases' => [release('v2.0.0')]]);
    RunCreep::dispatchSync($target);

    $driver->willReturn(['releases' => [release('v2.1.0', 'Webhook retries'), release('v2.0.0')]]);
    RunCreep::dispatchSync($target);

    $change = $target->changes()->sole();

    expect($change->field)->toBe('release')
        ->and($change->old_value)->toBe('v2.0.0')
        ->and($change->new_value)->toContain('v2.1.0')
        ->and($change->new_value)->toContain('Webhook retries')
        ->and($change->describe())->toContain('Shipped')
        ->and($target->changelogSnapshots()->count())->toBe(2);

    Notification::assertSentTo($target->user, TargetChanged::class);
});

it('logs several releases oldest first when it has catching up to do', function () {
    Notification::fake();

    $target = CreepTarget::factory()->changelog()->create();
    $driver = fakeDriver();

    $driver->willReturn(['releases' => [release('v2.0.0')]]);
    RunCreep::dispatchSync($target);

    $driver->willReturn([
        'releases' => [release('v2.2.0'), release('v2.1.0'), release('v2.0.0')],
    ]);
    RunCreep::dispatchSync($target);

    $changes = $target->changes()->orderBy('id')->get();

    expect($changes)->toHaveCount(2)
        ->and($changes[0]->new_value)->toContain('v2.1.0')
        ->and($changes[1]->new_value)->toContain('v2.2.0');
});

it('says nothing when the changelog has not moved', function () {
    Notification::fake();

    $target = CreepTarget::factory()->changelog()->create();
    $driver = fakeDriver();
    $payload = ['releases' => [release('v2.1.0'), release('v2.0.0')]];

    $driver->willReturn($payload);
    RunCreep::dispatchSync($target);

    $driver->willReturn($payload);
    RunCreep::dispatchSync($target);

    expect($target->changes()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('is not fooled by a page that reorders itself', function () {
    $target = CreepTarget::factory()->changelog()->create();
    $driver = fakeDriver();

    $driver->willReturn(['releases' => [release('v2.1.0'), release('v2.0.0')]]);
    RunCreep::dispatchSync($target);

    $driver->willReturn(['releases' => [release('v2.0.0'), release('v2.1.0')]]);
    RunCreep::dispatchSync($target);

    expect($target->changes()->count())->toBe(0);
});

it('keeps quiet about a release that falls off the bottom of the page', function () {
    $target = CreepTarget::factory()->changelog()->create();
    $driver = fakeDriver();

    $driver->willReturn(['releases' => [release('v2.1.0'), release('v2.0.0')]]);
    RunCreep::dispatchSync($target);

    $driver->willReturn(['releases' => [release('v2.1.0')]]);
    RunCreep::dispatchSync($target);

    expect($target->changes()->count())->toBe(0);
});

it('fails the run when the page turned out to have no releases on it', function () {
    $target = CreepTarget::factory()->changelog()->create();

    fakeDriver()->willReturn(['product' => 'Widgets', 'releases' => []]);

    RunCreep::dispatchSync($target);

    expect($target->runs()->sole()->status)->toBe(RunStatus::Failed)
        ->and($target->runs()->sole()->error)->toContain('at least one release')
        ->and($target->changelogSnapshots()->count())->toBe(0);
});

it('invents a changelog that ships something new on every creep', function () {
    Notification::fake();

    $target = CreepTarget::factory()->changelog()->create();

    fakeDriver();

    RunCreep::dispatchSync($target);
    RunCreep::dispatchSync($target);

    $snapshot = $target->changelogSnapshots()->orderByDesc('id')->first();

    expect($target->changes()->count())->toBe(1)
        ->and($snapshot->release_count)->toBeGreaterThan(1)
        ->and($snapshot->latest_version)->not->toBeNull();
});

it('hands the show page the releases it has read', function () {
    $target = CreepTarget::factory()->changelog()->create();

    ChangelogSnapshot::factory()->forTarget($target)->listing([
        release('v4.0.0', 'Bulk export'),
    ])->create();

    $this->actingAs($target->user)
        ->get(route('creep-targets.show', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('creep-targets/show')
            ->where('target.data.type', 'changelog')
            ->where('target.data.latest_changelog_snapshot.latest_version', 'v4.0.0')
            ->where('target.data.latest_changelog_snapshot.releases.0.features.0.title', 'Bulk export')
            // The price chart has nothing to draw for a changelog.
            ->has('history.data', 0)
        );
});

it('seeds a changelog with releases and changes, so a fresh install has one to look at', function () {
    $this->seed();

    $target = CreepTarget::query()->where('type', CreepType::Changelog)->sole();

    expect($target->changelogSnapshots()->count())->toBe(3)
        ->and($target->latestChangelogSnapshot->latest_version)->toBe('v2.4.0')
        ->and($target->latestChangelogSnapshot->release_count)->toBe(3)
        // Two releases landed while Creeper was watching.
        ->and($target->changes()->count())->toBe(2);
});
