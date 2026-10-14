<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Jobs\RunCompetitorDiscovery;
use App\Models\Business;
use App\Models\CompetitorDiscovery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CompetitorDiscoveryController extends Controller
{
    /**
     * Find a company's competitors (again). Spends the company's key, so it
     * refuses while one is already running — a second run would only pay
     * twice for the same list.
     */
    public function store(Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        if ($business->api_key_id === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Pick an API key for this company first.')]);

            return back();
        }

        if ($business->discoveries()->inFlight()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Creeper is already looking for competitors.')]);

            return back();
        }

        self::start($business);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Looking for competitors.')]);

        return back();
    }

    /**
     * Queue a discovery for a company, spending the key it has picked.
     */
    public static function start(Business $business): CompetitorDiscovery
    {
        $discovery = new CompetitorDiscovery;
        $discovery->forceFill([
            'api_key_id' => $business->api_key_id,
            'status' => RunStatus::Queued,
        ]);
        $business->discoveries()->save($discovery);

        RunCompetitorDiscovery::dispatch($discovery);

        return $discovery;
    }
}
