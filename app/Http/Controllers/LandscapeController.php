<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Jobs\RunLandscape;
use App\Models\ApiKey;
use App\Models\Business;
use App\Models\LandscapeAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LandscapeController extends Controller
{
    /**
     * Compare the business against its competitors again.
     */
    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);

        $validated = $request->validate([
            'api_key_id' => [
                'required',
                'integer',
                Rule::exists(ApiKey::class, 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        if (! $business->competitors()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Add a competitor first — there is nobody to compare against.')]);

            return to_route('dashboard');
        }

        // One at a time: a second run while the first is going would only pay
        // twice for the same answer.
        if ($business->landscapes()->inFlight()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A comparison is already running.')]);

            return to_route('dashboard');
        }

        $landscape = new LandscapeAnalysis;
        $landscape->forceFill([
            'api_key_id' => $validated['api_key_id'],
            'status' => RunStatus::Queued,
        ]);
        $business->landscapes()->save($landscape);

        RunLandscape::dispatch($landscape);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comparison started.')]);

        return to_route('dashboard');
    }
}
