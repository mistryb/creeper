<?php

namespace App\Http\Controllers;

use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Enums\PageStatus;
use App\Http\Requests\StoreWatchedPageRequest;
use App\Http\Requests\UpdateWatchedPageRequest;
use App\Http\Resources\CompetitorResource;
use App\Http\Resources\CreepChangeResource;
use App\Http\Resources\CreepRunResource;
use App\Http\Resources\WatchedPageResource;
use App\Jobs\RunCreep;
use App\Models\ApiKey;
use App\Models\Competitor;
use App\Models\WatchedPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WatchedPageController extends Controller
{
    /**
     * Show the form for watching another of a competitor's pages.
     */
    public function create(Request $request, Competitor $competitor): Response
    {
        $this->authorize('update', $competitor);

        return Inertia::render('watched-pages/create', [
            'competitor' => CompetitorResource::make($competitor),
            'frequencies' => $this->frequencyOptions(),
            'categories' => PageCategory::options(),
            // Creeping is paid for with one of the user's own keys, so the
            // form cannot be completed — or even usefully shown — without one.
            'apiKeys' => $this->apiKeyOptions($request),
        ]);
    }

    /**
     * Start watching one of a competitor's pages.
     */
    public function store(StoreWatchedPageRequest $request, Competitor $competitor): RedirectResponse
    {
        $this->authorize('update', $competitor);

        $watchedPage = $competitor->watchedPages()->create([
            ...$request->safe()->only(['url', 'watch_for', 'category', 'name', 'api_key_id', 'frequency', 'notify_on_change']),
            'status' => PageStatus::Active,
        ]);

        $watchedPage->forceFill([
            'next_creep_at' => $watchedPage->frequency->nextRunAfter(Carbon::now()),
        ])->save();

        // Nobody adds a page to wait a day for the first result.
        RunCreep::dispatch($watchedPage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping has started.')]);

        return to_route('watched-pages.show', $watchedPage);
    }

    /**
     * Everything known about one page.
     */
    public function show(Request $request, WatchedPage $watchedPage): Response
    {
        $this->authorize('view', $watchedPage);

        $watchedPage->load(['latestSnapshot', 'latestRun', 'apiKey', 'competitor']);

        return Inertia::render('watched-pages/show', [
            'watchedPage' => WatchedPageResource::make($watchedPage),
            'runs' => CreepRunResource::collection(
                $watchedPage->runs()->orderByDesc('started_at')->orderByDesc('id')->limit(20)->get()
            ),
            'changes' => CreepChangeResource::collection($watchedPage->changes()->latest('detected_at')->limit(30)->get()),
            'frequencies' => $this->frequencyOptions(),
            'categories' => PageCategory::options(),
            'apiKeys' => $this->apiKeyOptions($request),
            'isCreeping' => $watchedPage->runs()->whereIn('status', ['queued', 'running'])->exists(),
        ]);
    }

    /**
     * Change a page's settings.
     */
    public function update(UpdateWatchedPageRequest $request, WatchedPage $watchedPage): RedirectResponse
    {
        $this->authorize('update', $watchedPage);

        $watchedPage->fill($request->safe()->only([
            'url', 'watch_for', 'category', 'name', 'api_key_id', 'frequency', 'notify_on_change', 'status',
        ]));

        // Un-pausing, or moving to a different schedule, means recomputing
        // when the next sweep should pick this up.
        $watchedPage->next_creep_at = $watchedPage->status === PageStatus::Active
            ? $watchedPage->frequency->nextRunAfter($watchedPage->last_crept_at ?? Carbon::now())
            : null;

        if ($watchedPage->status === PageStatus::Active) {
            $watchedPage->consecutive_failures = 0;
        }

        $watchedPage->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page updated.')]);

        return to_route('watched-pages.show', $watchedPage);
    }

    /**
     * Stop creeping, and forget everything we found.
     */
    public function destroy(WatchedPage $watchedPage): RedirectResponse
    {
        $this->authorize('delete', $watchedPage);

        $watchedPage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page deleted.')]);

        return to_route('competitors.show', $watchedPage->competitor_id);
    }

    /**
     * The keys this user can put a page on, named as they named them.
     *
     * @return array<int, array<string, string>>
     */
    private function apiKeyOptions(Request $request): array
    {
        return $request->user()
            ->apiKeys()
            ->get()
            ->map(fn (ApiKey $key): array => [
                'value' => (string) $key->id,
                'label' => $key->label(),
            ])
            ->all();
    }

    /**
     * The schedules a page can be put on.
     *
     * @return array<int, array<string, string>>
     */
    private function frequencyOptions(): array
    {
        return array_map(
            fn (CreepFrequency $frequency): array => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ],
            CreepFrequency::cases(),
        );
    }
}
