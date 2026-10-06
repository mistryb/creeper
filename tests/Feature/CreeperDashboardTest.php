<?php

use App\Enums\PageCategory;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use App\Models\CreepChange;
use App\Models\LandscapeAnalysis;
use App\Models\User;
use App\Models\WatchedPage;

/**
 * A user with one business and one competitor of it, picked in the chooser.
 *
 * @return array{0: User, 1: Business, 2: Competitor}
 */
function dashboardOwner(): array
{
    $user = User::factory()->create();
    $business = Business::factory()->for($user)->create();
    $competitor = Competitor::factory()->for($business)->create(['name' => 'Acme']);
    $user->switchBusiness($business);

    return [$user, $business, $competitor];
}

it('asks for a business before there is one', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('business', null)
            ->missing('stats')
        );
});

it('counts the business picked in the chooser, and nothing else', function () {
    [$user, $business, $competitor] = dashboardOwner();
    $other = Business::factory()->for($user)->create();

    WatchedPage::factory()->for($competitor)->create();
    WatchedPage::factory()->for($competitor)->failing()->create();
    WatchedPage::factory()->count(2)->for(Competitor::factory()->for($other))->create();
    WatchedPage::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('business.data.id', $business->id)
            ->where('stats.competitors', 1)
            ->where('stats.watchedPages', 2)
            ->where('stats.parked', 1)
        );
});

it('counts each competitor\'s changes by kind of page, busiest first', function () {
    [$user, $business, $acme] = dashboardOwner();
    $quiet = Competitor::factory()->for($business)->create(['name' => 'Quiet Co']);

    $pricing = WatchedPage::factory()->for($acme)->create(['category' => PageCategory::Pricing]);
    $releases = WatchedPage::factory()->for($acme)->create(['category' => PageCategory::Releases]);

    CreepChange::factory()->count(2)->create(['watched_page_id' => $pricing->id]);
    CreepChange::factory()->create(['watched_page_id' => $releases->id]);
    CreepChange::factory()->create(['watched_page_id' => $releases->id, 'detected_at' => now()->subDays(60)]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.window', 30)
            ->has('activity', 2)
            ->where('activity.0.name', 'Acme')
            ->where('activity.0.total', 3)
            ->where('activity.0.by_category.pricing', 2)
            ->where('activity.0.by_category.releases', 1)
            ->where('activity.1.name', 'Quiet Co')
            ->where('activity.1.total', 0)
            ->where('stats.changes', 3)
        );

    $this->actingAs($user)
        ->get(route('dashboard', ['window' => 90]))
        ->assertInertia(fn ($page) => $page
            ->where('filters.window', 90)
            ->where('activity.0.total', 4)
        );
});

it('narrows the change feed by competitor and kind of page', function () {
    [$user, $business, $acme] = dashboardOwner();
    $globex = Competitor::factory()->for($business)->create();

    $acmePricing = WatchedPage::factory()->for($acme)->create(['category' => PageCategory::Pricing]);
    $acmeReleases = WatchedPage::factory()->for($acme)->create(['category' => PageCategory::Releases]);
    $globexPricing = WatchedPage::factory()->for($globex)->create(['category' => PageCategory::Pricing]);

    $wanted = CreepChange::factory()->create(['watched_page_id' => $acmePricing->id]);
    CreepChange::factory()->create(['watched_page_id' => $acmeReleases->id]);
    CreepChange::factory()->create(['watched_page_id' => $globexPricing->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('changes.data', 3));

    $this->actingAs($user)
        ->get(route('dashboard', ['competitor' => $acme->id, 'category' => 'pricing']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.competitor', $acme->id)
            ->where('filters.category', 'pricing')
            ->has('changes.data', 1)
            ->where('changes.data.0.id', $wanted->id)
            ->where('changes.data.0.watched_page.competitor.name', 'Acme')
            ->where('changes.data.0.watched_page.category', 'pricing')
        );
});

it('ignores filters that do not belong to the business', function () {
    [$user] = dashboardOwner();
    $theirs = Competitor::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['competitor' => $theirs->id, 'category' => 'weather', 'window' => 5]))
        ->assertInertia(fn ($page) => $page
            ->where('filters.competitor', null)
            ->where('filters.category', null)
            ->where('filters.window', 30)
        );
});

it('shows the latest comparison, and a newer failure alongside it', function () {
    [$user, $business, $competitor] = dashboardOwner();

    $good = LandscapeAnalysis::factory()->for($business)->create();
    $failed = LandscapeAnalysis::factory()->for($business)->failed()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('landscape.data.id', $good->id)
            ->where('landscape.data.report.rows.0.is_you', true)
            ->where('landscape.data.report.rows.0.name', $business->name)
            ->where('landscape.data.report.rows.1.name', 'Acme')
            ->where('landscape.data.report.rows.1.competitor_id', $competitor->id)
            ->where('latestLandscapeRun.data.id', $failed->id)
            ->where('isComparing', false)
        );
});

it('drops a deleted competitor from an old comparison', function () {
    [$user, $business, $competitor] = dashboardOwner();
    LandscapeAnalysis::factory()->for($business)->create();

    $competitor->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('landscape.data.report.rows', 1)
            ->has('landscape.data.report.map.points', 1)
        );
});

it('lists what is missing', function () {
    [$user, $business, $acme] = dashboardOwner();
    BusinessAnalysis::factory()->forSubject($business)->create(['finished_at' => now()->subDays(45)]);
    $parked = WatchedPage::factory()->for(Competitor::factory()->for($business)->create(['name' => 'Globex']))->failing()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(function ($page) use ($business, $acme, $parked) {
            $gaps = collect($page->toArray()['props']['gaps']);

            expect($gaps->pluck('type')->all())->toContain('business_stale', 'competitor_unwatched', 'competitor_unanalysed', 'page_parked')
                ->and($gaps->firstWhere('type', 'business_stale')['business_id'])->toBe($business->id)
                ->and($gaps->firstWhere('type', 'competitor_unwatched')['competitor_id'])->toBe($acme->id)
                ->and($gaps->firstWhere('type', 'page_parked')['watched_page_id'])->toBe($parked->id);

            return $page;
        });
});
