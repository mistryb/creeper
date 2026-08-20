<?php

namespace App\Actions;

use App\Creeping\ChangeDetector;
use App\Creeping\Data\ProductPayload;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\RunStatus;
use App\Enums\TargetStatus;
use App\Jobs\ReportCreepRunUsage;
use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Models\ProductSnapshot;
use App\Notifications\ProductChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a successful creep into a snapshot, a set of changes, and a notification.
 *
 * Both paths into Creeper end up here — the driver returning data inline, and
 * an agent posting to the signed callback later — so there is exactly one
 * place where a run becomes a result.
 */
class CompleteCreepRun
{
    public function __construct(private ChangeDetector $detector) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCreepPayload
     */
    public function handle(CreepRun $run, array $payload): ProductSnapshot
    {
        $product = ProductPayload::fromArray($payload);

        /** @var array{snapshot: ProductSnapshot, changes: array<int, CreepChange>} $outcome */
        $outcome = DB::transaction(function () use ($run, $product, $payload): array {
            $target = $run->target;
            $previous = $target->snapshots()->latest('captured_at')->first();

            /** @var ProductSnapshot $snapshot */
            $snapshot = $run->snapshot()->create([
                ...$product->toSnapshotAttributes(),
                'creep_target_id' => $target->id,
            ]);

            $run->forceFill(['raw_payload' => $payload])->save();
            $run->finish(RunStatus::Succeeded);

            // A success clears the failure streak and revives a parked target.
            $target->forceFill([
                'consecutive_failures' => 0,
                'status' => $target->status === TargetStatus::Failed
                    ? TargetStatus::Active
                    : $target->status,
            ])->save();

            $target->rescheduleFrom(Carbon::now());

            $changes = $previous
                ? array_map(
                    fn (array $attributes): CreepChange => CreepChange::create($attributes),
                    $this->detector->compare($previous, $snapshot),
                )
                : [];

            return ['snapshot' => $snapshot, 'changes' => $changes];
        });

        // Outside the transaction: neither a meter event nor a notification
        // should be able to fire against a snapshot that later rolls back.
        ReportCreepRunUsage::dispatch($run);

        if ($outcome['changes'] !== [] && $run->target->notify_on_change) {
            $run->target->user->notify(new ProductChanged($run->target, $outcome['changes']));
        }

        return $outcome['snapshot'];
    }
}
