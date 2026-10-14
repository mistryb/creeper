<?php

use App\Enums\DigestFrequency;
use App\Enums\RunStatus;
use App\Jobs\RunDigest;
use App\Models\Business;
use App\Models\Digest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

it('queues a digest for every company that is due, and moves its schedule on', function () {
    Queue::fake();
    Carbon::setTestNow('2026-10-14 08:00:00');

    $due = Business::factory()->withKey()->due()->create(['digest_frequency' => DigestFrequency::Daily, 'last_digest_at' => Carbon::now()->subDay()]);
    $later = Business::factory()->withKey()->create(['next_digest_at' => Carbon::now()->addHour()]);
    $never = Business::factory()->withKey()->create(['next_digest_at' => null]);

    $this->artisan('creeper:dispatch-due-digests')
        ->expectsOutputToContain('Queued 1 digest.')
        ->assertSuccessful();

    $digest = $due->digests()->sole();

    expect($digest->status)->toBe(RunStatus::Queued)
        ->and($digest->api_key_id)->toBe($due->api_key_id)
        ->and($digest->period_start->toDateTimeString())->toBe('2026-10-13 08:00:00')
        ->and($digest->period_end->toDateTimeString())->toBe('2026-10-14 08:00:00')
        ->and($due->fresh()->next_digest_at->toDateTimeString())->toBe('2026-10-15 08:00:00')
        ->and($later->digests()->count())->toBe(0)
        ->and($never->digests()->count())->toBe(0);

    Queue::assertPushed(RunDigest::class, 1);
});

it('skips a company whose digest is still being written', function () {
    Queue::fake();

    $business = Business::factory()->withKey()->due()->create();
    Digest::factory()->for($business)->running()->create();

    $this->artisan('creeper:dispatch-due-digests')->expectsOutputToContain('Queued 0 digests.');

    expect($business->digests()->count())->toBe(1);

    Queue::assertNothingPushed();
});

it('still queues a digest for a company whose key is gone, so the failure is recorded once', function () {
    Queue::fake();

    $business = Business::factory()->due()->create(['api_key_id' => null]);

    $this->artisan('creeper:dispatch-due-digests');

    expect($business->digests()->sole()->api_key_id)->toBeNull()
        ->and($business->fresh()->next_digest_at)->toBeGreaterThan(Carbon::now());

    Queue::assertPushed(RunDigest::class, 1);
});

it('is on the schedule every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'creeper:dispatch-due-digests'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});
