<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompetitorRequest;
use App\Http\Requests\UpdateCompetitorRequest;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CompetitorResource;
use App\Http\Resources\WatchedPageResource;
use App\Models\Business;
use App\Models\Competitor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompetitorController extends Controller
{
    /**
     * The companies a business competes with.
     */
    public function index(Business $business): Response
    {
        $this->authorize('view', $business);

        return Inertia::render('competitors/index', [
            'business' => BusinessResource::make($business),
            'competitors' => CompetitorResource::collection(
                $business->competitors()->withCount('watchedPages')->get()
            ),
        ]);
    }

    /**
     * Show the form for adding a competitor.
     */
    public function create(Business $business): Response
    {
        $this->authorize('update', $business);

        return Inertia::render('competitors/create', [
            'business' => BusinessResource::make($business),
        ]);
    }

    /**
     * Add a competitor to a business.
     */
    public function store(StoreCompetitorRequest $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $competitor = $business->competitors()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor added.')]);

        return to_route('competitors.show', $competitor);
    }

    /**
     * One competitor: who they are, and the pages of theirs being watched.
     */
    public function show(Competitor $competitor): Response
    {
        $this->authorize('view', $competitor);

        return Inertia::render('competitors/show', [
            'business' => BusinessResource::make($competitor->business),
            'competitor' => CompetitorResource::make($competitor),
            'watchedPages' => WatchedPageResource::collection(
                $competitor->watchedPages()
                    ->with(['latestSnapshot', 'latestRun'])
                    ->latest()
                    ->get()
            ),
        ]);
    }

    /**
     * Change what is known about a competitor.
     */
    public function update(UpdateCompetitorRequest $request, Competitor $competitor): RedirectResponse
    {
        $this->authorize('update', $competitor);

        $competitor->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor updated.')]);

        return to_route('competitors.show', $competitor);
    }

    /**
     * Stop watching a competitor, and forget everything found about them.
     */
    public function destroy(Competitor $competitor): RedirectResponse
    {
        $this->authorize('delete', $competitor);

        $business = $competitor->business;

        $competitor->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Competitor deleted.')]);

        return to_route('businesses.competitors.index', $business);
    }
}
