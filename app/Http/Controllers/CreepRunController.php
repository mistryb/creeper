<?php

namespace App\Http\Controllers;

use App\Enums\TargetStatus;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CreepRunController extends Controller
{
    /**
     * Creep a target right now, rather than waiting for its schedule.
     */
    public function store(CreepTarget $creepTarget): RedirectResponse
    {
        $this->authorize('update', $creepTarget);

        // Paused means paused, on demand as much as on a schedule — otherwise
        // pausing would do nothing at all to a target whose frequency is
        // already "only when I ask".
        if ($creepTarget->status === TargetStatus::Paused) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This target is paused. Resume it to creep again.')]);

            return back();
        }

        // RunCreep is unique per target, so a duplicate dispatch would be
        // dropped in silence. Say so instead of pretending it worked.
        if ($creepTarget->runs()->whereIn('status', ['queued', 'running'])->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This target is already being crept.')]);

            return back();
        }

        RunCreep::dispatch($creepTarget);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping now.')]);

        return back();
    }
}
