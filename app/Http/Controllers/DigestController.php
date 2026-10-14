<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessResource;
use App\Http\Resources\CompetitorResource;
use App\Http\Resources\DigestResource;
use App\Jobs\RunDigest;
use App\Models\Business;
use App\Models\Digest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DigestController extends Controller
{
    /**
     * How many past digests the history lists.
     */
    private const HISTORY = 30;

    /**
     * Every digest written about a company, newest first.
     */
    public function index(Business $business): Response
    {
        $this->authorize('view', $business);

        return Inertia::render('digests/index', [
            'business' => BusinessResource::make($business),
            'digests' => DigestResource::collection(
                $business->digests()->reorder()->latest('id')->limit(self::HISTORY)->get()
            ),
        ]);
    }

    /**
     * Send a digest now, covering everything since the last one, rather than
     * waiting for the schedule. Refuses while one is already running.
     */
    public function store(Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        if ($business->api_key_id === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Pick an API key for this company first.')]);

            return back();
        }

        if ($business->digests()->inFlight()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A digest is already being written.')]);

            return back();
        }

        $digest = Digest::queueFor($business);

        RunDigest::dispatch($digest);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Writing the digest. It will be emailed to you when it is ready.')]);

        return back();
    }

    /**
     * One digest in full.
     */
    public function show(Digest $digest): Response
    {
        $this->authorize('view', $digest);

        $business = $digest->business;

        return Inertia::render('digests/show', [
            'business' => BusinessResource::make($business),
            'digest' => DigestResource::make($digest),
            'competitors' => CompetitorResource::collection($business->competitors()->get()),
        ]);
    }
}
