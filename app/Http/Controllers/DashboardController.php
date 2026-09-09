<?php

namespace App\Http\Controllers;

use App\Enums\TargetStatus;
use App\Http\Resources\CreepChangeResource;
use App\Http\Resources\CreepRunResource;
use App\Http\Resources\CreepTargetResource;
use App\Models\CreepChange;
use App\Models\CreepRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * An overview of everything being crept.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $targetIds = $user->creepTargets()->pluck('id');

        return Inertia::render('dashboard', [
            'stats' => [
                'targets' => $targetIds->count(),
                'active' => $user->creepTargets()->where('status', TargetStatus::Active)->count(),
                'failing' => $user->creepTargets()->where('status', TargetStatus::Failed)->count(),
                'changesThisWeek' => CreepChange::query()
                    ->whereIn('creep_target_id', $targetIds)
                    ->where('detected_at', '>=', Carbon::now()->subWeek())
                    ->count(),
            ],
            'recentChanges' => CreepChangeResource::collection(
                CreepChange::query()
                    ->whereIn('creep_target_id', $targetIds)
                    ->with('target')
                    ->latest('detected_at')
                    ->limit(10)
                    ->get()
            ),
            'recentRuns' => CreepRunResource::collection(
                CreepRun::query()
                    ->whereIn('creep_target_id', $targetIds)
                    ->with('target')
                    ->orderByDesc('started_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            ),
            'watchlist' => CreepTargetResource::collection(
                $user->creepTargets()
                    ->with(['latestSnapshot', 'latestChangelogSnapshot', 'latestRun'])
                    ->latest()
                    ->limit(5)
                    ->get()
            ),
        ]);
    }
}
