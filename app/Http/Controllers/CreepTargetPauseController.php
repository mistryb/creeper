<?php

namespace App\Http\Controllers;

use App\Models\CreepTarget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Pausing is the middle ground between creeping and deleting: the schedule
 * stops, but the price history, the run log and the changes all stay put.
 *
 * It lives outside {@see CreepTargetController} because it is one click, not
 * a form — a target should be stoppable without editing anything else about it.
 */
class CreepTargetPauseController extends Controller
{
    /**
     * Stop creeping a target.
     */
    public function store(CreepTarget $creepTarget): RedirectResponse
    {
        $this->authorize('update', $creepTarget);

        $creepTarget->pause();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping paused. Nothing has been deleted.')]);

        return back();
    }

    /**
     * Start creeping it again.
     */
    public function destroy(CreepTarget $creepTarget): RedirectResponse
    {
        $this->authorize('update', $creepTarget);

        $creepTarget->resume();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping resumed.')]);

        return back();
    }
}
