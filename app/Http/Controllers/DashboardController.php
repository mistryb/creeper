<?php

namespace App\Http\Controllers;

use App\Enums\PageCategory;
use App\Enums\PageStatus;
use App\Enums\RunStatus;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CreepChangeResource;
use App\Http\Resources\LandscapeAnalysisResource;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\BusinessAnalysis;
use App\Models\Competitor;
use App\Models\CreepChange;
use App\Models\WatchedPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The windows activity can be counted over, in days.
     */
    private const WINDOWS = [7, 30, 90];

    /**
     * An analysis older than this is flagged as worth running again.
     */
    private const STALE_DAYS = 30;

    /**
     * The business picked in the sidebar chooser, set against its competitors:
     * where it stands, how they compare, who is moving, and what is missing.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->selectedBusiness();

        if ($business === null) {
            return Inertia::render('dashboard', ['business' => null]);
        }

        $filters = $this->filters($request, $business);
        $since = Carbon::now()->subDays($filters['window']);

        return Inertia::render('dashboard', [
            'business' => BusinessResource::make($business),
            'filters' => $filters,
            'categories' => PageCategory::options(),
            'competitors' => fn (): array => $business->competitors()->get(['id', 'name'])->toArray(),
            'stats' => fn (): array => $this->stats($business, $since),
            'landscape' => fn (): ?LandscapeAnalysisResource => $this->landscape($business, RunStatus::Succeeded),
            'latestLandscapeRun' => fn (): ?LandscapeAnalysisResource => $this->landscape($business),
            'isComparing' => fn (): bool => $business->landscapes()->inFlight()->exists(),
            'apiKeys' => fn (): array => $request->user()->apiKeys()->get()->map(fn (ApiKey $key): array => [
                'value' => (string) $key->id,
                'label' => $key->label(),
            ])->all(),
            'activity' => fn (): array => $this->activity($business, $since),
            'changes' => fn () => CreepChangeResource::collection($this->changes($business, $filters, $since)),
            'gaps' => fn (): array => $this->gaps($business),
        ]);
    }

    /**
     * The window, competitor and category asked for, each checked against what
     * is allowed so a hand-edited URL cannot reach another business's data.
     *
     * @return array{window: int, competitor: int|null, category: string|null}
     */
    private function filters(Request $request, Business $business): array
    {
        $window = $request->integer('window', 30);
        $competitor = $request->integer('competitor') ?: null;
        $category = PageCategory::tryFrom((string) $request->query('category'));

        return [
            'window' => in_array($window, self::WINDOWS, true) ? $window : 30,
            'competitor' => $competitor !== null && $business->competitors()->whereKey($competitor)->exists() ? $competitor : null,
            'category' => $category?->value,
        ];
    }

    /**
     * @return array{competitors: int, watchedPages: int, changes: int, parked: int}
     */
    private function stats(Business $business, Carbon $since): array
    {
        return [
            'competitors' => $business->competitors()->count(),
            'watchedPages' => $this->pages($business)->count(),
            'changes' => $this->changesQuery($business)->where('detected_at', '>=', $since)->count(),
            'parked' => $this->pages($business)->where('status', PageStatus::Failed)->count(),
        ];
    }

    /**
     * The newest landscape run, of a given status or any.
     */
    private function landscape(Business $business, ?RunStatus $status = null): ?LandscapeAnalysisResource
    {
        $landscape = $business->landscapes()
            ->reorder()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->with('business.competitors')
            ->first();

        return $landscape === null ? null : LandscapeAnalysisResource::make($landscape);
    }

    /**
     * How many changes each competitor's pages showed in the window, split by
     * the kind of page they were on. Every competitor gets a row, quiet ones
     * included, busiest first.
     *
     * @return list<array<string, mixed>>
     */
    private function activity(Business $business, Carbon $since): array
    {
        $counts = CreepChange::query()
            ->join('watched_pages', 'watched_pages.id', '=', 'creep_changes.watched_page_id')
            ->join('competitors', 'competitors.id', '=', 'watched_pages.competitor_id')
            ->where('competitors.business_id', $business->id)
            ->where('creep_changes.detected_at', '>=', $since)
            ->groupBy('competitors.id', 'watched_pages.category')
            ->selectRaw('competitors.id as competitor_id, watched_pages.category as category, count(*) as total, max(creep_changes.detected_at) as last_change_at')
            ->toBase()
            ->get()
            ->groupBy('competitor_id');

        $rows = $business->competitors()->withCount('watchedPages')->get()->map(function (Competitor $competitor) use ($counts): array {
            $mine = $counts->get($competitor->id, collect());
            $byCategory = [];

            foreach (PageCategory::cases() as $category) {
                $byCategory[$category->value] = (int) $mine->firstWhere('category', $category->value)?->total;
            }

            $last = $mine->max('last_change_at');

            return [
                'competitor_id' => $competitor->id,
                'name' => $competitor->name,
                'watched_pages' => $competitor->watched_pages_count,
                'total' => array_sum($byCategory),
                'by_category' => $byCategory,
                'last_change_at' => $last === null ? null : Carbon::parse($last)->toIso8601String(),
            ];
        });

        return $rows->sortByDesc('total')->values()->all();
    }

    /**
     * The change feed, narrowed by the filters.
     *
     * @param  array{window: int, competitor: int|null, category: string|null}  $filters
     * @return Collection<int, CreepChange>
     */
    private function changes(Business $business, array $filters, Carbon $since): Collection
    {
        return $this->changesQuery($business)
            ->where('detected_at', '>=', $since)
            ->when($filters['competitor'], fn (Builder $query, int $competitor) => $query->whereHas(
                'watchedPage',
                fn (Builder $page) => $page->where('competitor_id', $competitor),
            ))
            ->when($filters['category'], fn (Builder $query, string $category) => $query->whereHas(
                'watchedPage',
                fn (Builder $page) => $page->where('category', $category),
            ))
            ->with('watchedPage.competitor')
            ->latest('detected_at')
            ->latest('id')
            ->limit(30)
            ->get();
    }

    /**
     * What is missing or broken, so the comparison above it can be trusted:
     * analyses never run or gone stale, competitors nobody is watching, and
     * pages that have stopped being read.
     *
     * @return list<array{type: string, name: string, business_id?: int, competitor_id?: int, watched_page_id?: int, since?: string|null}>
     */
    private function gaps(Business $business): array
    {
        $gaps = [];
        $stale = Carbon::now()->subDays(self::STALE_DAYS);

        $businessAnalysis = $this->latestAnalysis($business);

        if ($businessAnalysis === null || $businessAnalysis->finished_at?->lt($stale)) {
            $gaps[] = [
                'type' => $businessAnalysis === null ? 'business_unanalysed' : 'business_stale',
                'name' => $business->name,
                'business_id' => $business->id,
                'since' => $businessAnalysis?->finished_at?->toIso8601String(),
            ];
        }

        foreach ($business->competitors()->withCount('watchedPages')->get() as $competitor) {
            if ($competitor->watched_pages_count === 0) {
                $gaps[] = ['type' => 'competitor_unwatched', 'name' => $competitor->name, 'competitor_id' => $competitor->id];
            }

            $analysis = $this->latestAnalysis($competitor);

            if ($analysis === null || $analysis->finished_at?->lt($stale)) {
                $gaps[] = [
                    'type' => $analysis === null ? 'competitor_unanalysed' : 'competitor_stale',
                    'name' => $competitor->name,
                    'competitor_id' => $competitor->id,
                    'since' => $analysis?->finished_at?->toIso8601String(),
                ];
            }
        }

        $broken = $this->pages($business)
            ->where(fn (Builder $query) => $query->where('status', PageStatus::Failed)->orWhereNull('api_key_id'))
            ->get();

        foreach ($broken as $page) {
            $gaps[] = [
                'type' => $page->status === PageStatus::Failed ? 'page_parked' : 'page_keyless',
                'name' => $page->displayName(),
                'watched_page_id' => $page->id,
            ];
        }

        return $gaps;
    }

    private function latestAnalysis(Business|Competitor $subject): ?BusinessAnalysis
    {
        return $subject->analyses()->reorder()->where('status', RunStatus::Succeeded)->latest('id')->first();
    }

    /**
     * Every page watched on one of the business's competitors.
     *
     * @return Builder<WatchedPage>
     */
    private function pages(Business $business): Builder
    {
        return WatchedPage::query()->whereHas(
            'competitor',
            fn (Builder $query) => $query->where('business_id', $business->id),
        );
    }

    /**
     * Every change on one of the business's competitors' pages.
     *
     * @return Builder<CreepChange>
     */
    private function changesQuery(Business $business): Builder
    {
        return CreepChange::query()->whereHas(
            'watchedPage.competitor',
            fn (Builder $query) => $query->where('business_id', $business->id),
        );
    }
}
