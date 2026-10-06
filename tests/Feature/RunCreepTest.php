<?php

use App\Enums\ChangeKind;
use App\Enums\PageStatus;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\WatchedPage;
use App\Notifications\PageChanged;
use Illuminate\Support\Facades\Notification;

it('records a run and a snapshot', function () {
    $watchedPage = WatchedPage::factory()->create();

    fakeDriver()->willReturn(reading(['Pro plan' => '$20/month', 'Free tier' => 'Yes']));

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();
    $snapshot = $watchedPage->snapshots()->sole();

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->driver)->toBe('fake')
        ->and($run->finished_at)->not->toBeNull()
        ->and($snapshot->summary)->toBe('What the page says.')
        ->and($snapshot->facts)->toBe([
            ['label' => 'Pro plan', 'value' => '$20/month'],
            ['label' => 'Free tier', 'value' => 'Yes'],
        ]);
});

it('moves the schedule on after a successful run', function () {
    $watchedPage = WatchedPage::factory()->due()->create();

    fakeDriver();

    RunCreep::dispatchSync($watchedPage);

    $watchedPage->refresh();

    expect($watchedPage->last_crept_at)->not->toBeNull()
        ->and($watchedPage->next_creep_at)->not->toBeNull()
        ->and($watchedPage->next_creep_at->isFuture())->toBeTrue();
});

it('records a change when a fact moves', function () {
    Notification::fake();

    $watchedPage = WatchedPage::factory()->create();

    $driver = fakeDriver();
    $driver->willReturn(reading(['Pro plan' => '$20/month']));
    RunCreep::dispatchSync($watchedPage);

    $driver->willReturn(reading(['Pro plan' => '$25/month']));
    RunCreep::dispatchSync($watchedPage);

    $change = $watchedPage->changes()->sole();

    expect($change->label)->toBe('Pro plan')
        ->and($change->kind)->toBe(ChangeKind::Changed)
        ->and($change->old_value)->toBe('$20/month')
        ->and($change->new_value)->toBe('$25/month')
        ->and($watchedPage->snapshots()->count())->toBe(2);

    Notification::assertSentTo($watchedPage->owner(), PageChanged::class);
});

it('emails a subject that names the competitor and leads with a value that moved', function () {
    Notification::fake();

    $watchedPage = WatchedPage::factory()->create(['name' => 'Pricing']);

    $driver = fakeDriver();
    $driver->willReturn(reading(['Pro plan' => '$20/month']));
    RunCreep::dispatchSync($watchedPage);

    $driver->willReturn(reading(['Team plan' => '$99/month', 'Pro plan' => '$25/month']));
    RunCreep::dispatchSync($watchedPage);

    Notification::assertSentTo($watchedPage->owner(), PageChanged::class, function (PageChanged $notification) use ($watchedPage): bool {
        $mail = $notification->toMail($watchedPage->owner());

        return $mail->subject === "{$watchedPage->competitor->name} · Pricing: Pro plan: \$20/month → \$25/month"
            && collect($mail->introLines)->contains('- New: Team plan — $99/month');
    });
});

it('records nothing when nothing moved', function () {
    Notification::fake();

    $watchedPage = WatchedPage::factory()->create();

    $driver = fakeDriver();
    $payload = reading(['Pro plan' => '$20/month']);

    $driver->willReturn($payload);
    RunCreep::dispatchSync($watchedPage);

    $driver->willReturn($payload);
    RunCreep::dispatchSync($watchedPage);

    expect($watchedPage->changes()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('stays quiet when the page has notifications turned off', function () {
    Notification::fake();

    $watchedPage = WatchedPage::factory()->create(['notify_on_change' => false]);

    $driver = fakeDriver();
    $driver->willReturn(reading(['Pro plan' => '$20/month']));
    RunCreep::dispatchSync($watchedPage);

    $driver->willReturn(reading(['Pro plan' => '$25/month']));
    RunCreep::dispatchSync($watchedPage);

    expect($watchedPage->changes()->count())->toBe(1);

    Notification::assertNothingSent();
});

it('marks the run failed when the driver reports a failure', function () {
    $watchedPage = WatchedPage::factory()->create();

    fakeDriver()->willFail('That page is not a product.');

    RunCreep::dispatchSync($watchedPage);

    $run = $watchedPage->runs()->sole();
    $watchedPage->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toBe('That page is not a product.')
        ->and($watchedPage->consecutive_failures)->toBe(1)
        ->and($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->snapshots()->count())->toBe(0);
});

it('parks a page that keeps failing', function () {
    $watchedPage = WatchedPage::factory()->create();

    fakeDriver()->willFail();

    foreach (range(1, WatchedPage::FAILURE_LIMIT) as $ignored) {
        RunCreep::dispatchSync($watchedPage);
    }

    $watchedPage->refresh();

    expect($watchedPage->consecutive_failures)->toBe(WatchedPage::FAILURE_LIMIT)
        ->and($watchedPage->status)->toBe(PageStatus::Failed)
        ->and($watchedPage->next_creep_at)->toBeNull();
});

it('revives a parked page when it succeeds again', function () {
    $watchedPage = WatchedPage::factory()->failing()->create();

    fakeDriver()->willReturn(reading(['Status' => 'Back from the dead']));

    RunCreep::dispatchSync($watchedPage);

    $watchedPage->refresh();

    expect($watchedPage->status)->toBe(PageStatus::Active)
        ->and($watchedPage->consecutive_failures)->toBe(0);
});

it('fails the run when the payload has nothing usable in it', function () {
    $watchedPage = WatchedPage::factory()->create();

    fakeDriver()->willReturn(['facts' => [['label' => 'Pro plan', 'value' => '$20']]]);

    RunCreep::dispatchSync($watchedPage);

    expect($watchedPage->runs()->sole()->status)->toBe(RunStatus::Failed)
        ->and($watchedPage->snapshots()->count())->toBe(0);
});

/**
 * A reading of the shape a reader returns: a summary and some facts.
 *
 * @param  array<string, string>  $facts  Label => value.
 * @return array<string, mixed>
 */
function reading(array $facts): array
{
    return [
        'summary' => 'What the page says.',
        'facts' => array_map(
            fn (string $label, string $value): array => ['label' => $label, 'value' => $value],
            array_keys($facts),
            array_values($facts),
        ),
    ];
}
