<?php

namespace App\Http\Controllers;

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
use App\Models\ApiKey;
use App\Models\CreepTarget;
use App\Models\ProductSnapshot;
use Illuminate\Database\Eloquent\Collection;
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
            ->with(['latestSnapshot', 'latestChangelogSnapshot', 'latestRun'])
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
    public function create(Request $request): Response
    {
        $this->authorize('create', CreepTarget::class);

        return Inertia::render('creep-targets/create', [
            'types' => $this->typeOptions(),
            'frequencies' => $this->frequencyOptions(),
            // Creeping is paid for with one of the user's own keys, so the
            // form cannot be completed — or even usefully shown — without one.
            'apiKeys' => $this->apiKeyOptions($request),
        ]);
    }

    /**
     * Start creeping something.
     */
    public function store(StoreCreepTargetRequest $request): RedirectResponse
    {
        $this->authorize('create', CreepTarget::class);

        $target = $request->user()->creepTargets()->create([
            ...$request->safe()->only(['url', 'name', 'api_key_id', 'frequency', 'notify_on_change']),
            'type' => $request->safe()->enum('type', CreepType::class),
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
    public function show(Request $request, CreepTarget $creepTarget): Response
    {
        $this->authorize('view', $creepTarget);

        $creepTarget->load(['latestSnapshot', 'latestChangelogSnapshot', 'latestRun', 'apiKey']);

        return Inertia::render('creep-targets/show', [
            'target' => CreepTargetResource::make($creepTarget),
            'history' => ProductSnapshotResource::collection($this->priceHistory($creepTarget)),
            'runs' => CreepRunResource::collection(
                $creepTarget->runs()->orderByDesc('started_at')->orderByDesc('id')->limit(20)->get()
            ),
            'changes' => CreepChangeResource::collection($creepTarget->changes()->latest('detected_at')->limit(30)->get()),
            'frequencies' => $this->frequencyOptions(),
            'apiKeys' => $this->apiKeyOptions($request),
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
            'url', 'name', 'api_key_id', 'frequency', 'notify_on_change', 'status',
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
     * The price chart's series. Only product targets have one.
     *
     * @return Collection<int, ProductSnapshot>
     */
    private function priceHistory(CreepTarget $target): Collection
    {
        if ($target->type !== CreepType::Product) {
            return new Collection;
        }

        return $target->snapshots()
            ->where('captured_at', '>=', Carbon::now()->subDays(self::HISTORY_DAYS))
            ->orderBy('captured_at')
            ->get();
    }

    /**
     * The kinds of creeping on offer. How each one is pitched is the form's
     * business — see `resources/js/lib/creep-types.ts`.
     *
     * @return array<int, array<string, string>>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (CreepType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            CreepType::cases(),
        );
    }

    /**
     * The keys this user can put a target on, named as they named them.
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
     * The schedules a target can be put on.
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
