<?php

namespace App\Http\Controllers;

use App\Billing\PlanLimits;
use App\Enums\CreepFrequency;
use App\Enums\CreepType;
use App\Enums\TargetStatus;
use App\Http\Requests\StoreCreepTargetRequest;
use App\Http\Requests\UpdateCreepTargetRequest;
use App\Http\Resources\CreepChangeResource;
use App\Http\Resources\CreepRunResource;
use App\Http\Resources\CreepTargetResource;
use App\Http\Resources\ProductSnapshotResource;
use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CreepTargetController extends Controller
{
    /**
     * How much price history the chart draws.
     */
    private const HISTORY_DAYS = 90;

    /**
     * List everything this user is creeping.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CreepTarget::class);

        $targets = $request->user()
            ->creepTargets()
            ->with(['latestSnapshot', 'latestRun'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('creep-targets/index', [
            'targets' => CreepTargetResource::collection($targets),
        ]);
    }

    /**
     * Show the form for pointing Creeper at something new.
     */
    public function create(Request $request, PlanLimits $limits): Response
    {
        $this->authorize('create', CreepTarget::class);

        return Inertia::render('creep-targets/create', [
            'frequencies' => $this->frequencyOptions($request, $limits),
            'targetsRemaining' => $limits->targetsRemaining($request->user()),
        ]);
    }

    /**
     * Start creeping something.
     */
    public function store(StoreCreepTargetRequest $request): RedirectResponse
    {
        $this->authorize('create', CreepTarget::class);

        $target = $request->user()->creepTargets()->create([
            ...$request->safe()->only(['url', 'name', 'frequency', 'notify_on_change']),
            'type' => CreepType::Product,
            'status' => TargetStatus::Active,
        ]);

        $target->forceFill([
            'next_creep_at' => $target->frequency->nextRunAfter(Carbon::now()),
        ])->save();

        // Nobody adds a target to wait a day for the first result.
        RunCreep::dispatch($target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Creeping has started.')]);

        return to_route('creep-targets.show', $target);
    }

    /**
     * Everything known about one target.
     */
    public function show(Request $request, CreepTarget $creepTarget, PlanLimits $limits): Response
    {
        $this->authorize('view', $creepTarget);

        $creepTarget->load(['latestSnapshot', 'latestRun']);

        $history = $creepTarget->snapshots()
            ->where('captured_at', '>=', Carbon::now()->subDays(self::HISTORY_DAYS))
            ->orderBy('captured_at')
            ->get();

        return Inertia::render('creep-targets/show', [
            'target' => CreepTargetResource::make($creepTarget),
            'history' => ProductSnapshotResource::collection($history),
            'runs' => CreepRunResource::collection(
                $creepTarget->runs()->orderByDesc('started_at')->orderByDesc('id')->limit(20)->get()
            ),
            'changes' => CreepChangeResource::collection($creepTarget->changes()->latest('detected_at')->limit(30)->get()),
            'frequencies' => $this->frequencyOptions($request, $limits),
            'statuses' => array_map(
                fn (TargetStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                [TargetStatus::Active, TargetStatus::Paused],
            ),
            'isCreeping' => $creepTarget->runs()->whereIn('status', ['queued', 'running'])->exists(),
        ]);
    }

    /**
     * Change a target's settings.
     */
    public function update(UpdateCreepTargetRequest $request, CreepTarget $creepTarget): RedirectResponse
    {
        $this->authorize('update', $creepTarget);

        $creepTarget->fill($request->safe()->only([
            'url', 'name', 'frequency', 'notify_on_change', 'status',
        ]));

        // Un-pausing, or moving to a different schedule, means recomputing
        // when the next sweep should pick this up.
        $creepTarget->next_creep_at = $creepTarget->status === TargetStatus::Active
            ? $creepTarget->frequency->nextRunAfter($creepTarget->last_crept_at ?? Carbon::now())
            : null;

        if ($creepTarget->status === TargetStatus::Active) {
            $creepTarget->consecutive_failures = 0;
        }

        $creepTarget->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target updated.')]);

        return to_route('creep-targets.show', $creepTarget);
    }

    /**
     * Stop creeping, and forget everything we found.
     */
    public function destroy(CreepTarget $creepTarget): RedirectResponse
    {
        $this->authorize('delete', $creepTarget);

        $creepTarget->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target deleted.')]);

        return to_route('creep-targets.index');
    }

    /**
     * The schedules this user's plan lets them pick from.
     *
     * @return array<int, array<string, string>>
     */
    private function frequencyOptions(Request $request, PlanLimits $limits): array
    {
        return array_map(
            fn (CreepFrequency $frequency): array => [
                'value' => $frequency->value,
                'label' => $frequency->label(),
            ],
            $limits->allowedFrequencies($request->user()),
        );
    }
}
