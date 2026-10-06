<?php

use App\Enums\ChangeKind;
use App\Enums\RunStatus;
use App\Jobs\RunCreep;
use App\Models\Competitor;
use App\Models\CreepRun;
use App\Models\PageSnapshot;
use App\Models\User;
use App\Models\WatchedPage;
use Illuminate\Support\Facades\Queue;

it('creeps a page on demand', function () {
    Queue::fake();

    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->from(route('watched-pages.show', $watchedPage))
        ->post(route('watched-pages.runs.store', $watchedPage))
        ->assertRedirect(route('watched-pages.show', $watchedPage));

    Queue::assertPushed(RunCreep::class, fn (RunCreep $job): bool => $job->watchedPage->is($watchedPage));
});

it('does not queue a second creep while one is in flight', function () {
    Queue::fake();

    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();
    CreepRun::factory()->running()->create(['watched_page_id' => $watchedPage->id]);

    $this->actingAs($user)
        ->from(route('watched-pages.show', $watchedPage))
        ->post(route('watched-pages.runs.store', $watchedPage))
        ->assertRedirect();

    Queue::assertNothingPushed();
});

it('will not let anyone creep somebody else\'s page', function () {
    Queue::fake();

    $watchedPage = WatchedPage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('watched-pages.runs.store', $watchedPage))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('shows a page with its latest reading, runs and changes', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();

    $first = PageSnapshot::factory()->of($watchedPage, ['Pro plan' => '$20/month'])->create(['captured_at' => now()->subDay()]);
    $second = PageSnapshot::factory()->of($watchedPage, ['Pro plan' => '$25/month'])->create(['captured_at' => now()]);

    $watchedPage->changes()->create([
        'from_snapshot_id' => $first->id,
        'to_snapshot_id' => $second->id,
        'label' => 'Pro plan',
        'old_value' => '$20/month',
        'new_value' => '$25/month',
        'kind' => ChangeKind::Changed,
        'detected_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('watched-pages.show', $watchedPage))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('watched-pages/show')
            ->where('watchedPage.data.id', $watchedPage->id)
            ->where('watchedPage.data.latest_snapshot.facts.0.value', '$25/month')
            ->where('watchedPage.data.watch_for', $watchedPage->watch_for)
            ->has('runs.data', 2)
            ->has('changes.data', 1)
            ->where('isCreeping', false)
        );
});

it('reports a page that is mid-creep so the button can wait', function () {
    $user = User::factory()->create();
    $watchedPage = WatchedPage::factory()->forUser($user)->create();
    CreepRun::factory()->running()->create(['watched_page_id' => $watchedPage->id]);

    $this->actingAs($user)
        ->get(route('watched-pages.show', $watchedPage))
        ->assertInertia(fn ($page) => $page->where('isCreeping', true));
});

it('offers every schedule on the new page form', function () {
    $competitor = Competitor::factory()->forUser($user = User::factory()->create())->create();

    $this->actingAs($user)
        ->get(route('competitors.watched-pages.create', $competitor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('watched-pages/create')
            ->has('frequencies', 4)
        );
});

it('marks the run finished with a duration', function () {
    $run = CreepRun::factory()->running()->create(['started_at' => now()->subSeconds(2)]);

    $run->finish(RunStatus::Succeeded);

    expect($run->status)->toBe(RunStatus::Succeeded)
        ->and($run->duration_ms)->toBeGreaterThanOrEqual(2000);
});
