<?php

namespace App\Http\Controllers;

use App\Enums\PageStatus;
use App\Jobs\RunCreep;
use App\Models\WatchedPage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CreepRunController extends Controller
{
    /**
     * Creep a page right now, rather than waiting for its schedule.
     */
    public function store(WatchedPage $watchedPage): RedirectResponse
    {
        $this->authorize('update', $watchedPage);

        // Paused means paused, on demand as much as on a schedule — otherwise
        // pausing would do nothing at all to a page whose frequency is
        // already "only when I ask".
        if ($watchedPage->status === PageStatus::Paused) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This page is paused. Resume it to creep again.')]);

            return back();
        }

        // RunCreep is unique per page, so a duplicate dispatch would be
        // dropped in silence. Say so instead of pretending it worked.
        if ($watchedPage->runs()->whereIn('status', ['queued', 'running'])->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This page is already being crept.')]);

            return back();
        }

        RunCreep::dispatch($watchedPage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping now.')]);

        return back();
    }
}
