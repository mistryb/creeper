<?php

namespace App\Actions;

use App\Creeping\Contracts\CreepInstructions;
use App\Creeping\Data\Reading;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\RunStatus;
use App\Enums\TargetStatus;
use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Notifications\TargetChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a successful creep into a reading, a set of changes, and a notification.
 *
 * Both paths into Creeper end up here — the driver returning data inline, and
 * an agent posting to the signed callback later — so there is exactly one
 * place where a run becomes a result.
 *
 * What a reading actually is depends on the target's type, and that is the
 * only thing this action delegates: the target's
 * {@see CreepInstructions} validate the payload, file
 * the snapshot and say what moved. Everything around it — the transaction, the
 * failure streak, the schedule, the e-mail — is the same for every type.
 */
class CompleteCreepRun
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCreepPayload
     */
    public function handle(CreepRun $run, array $payload): Reading
    {
        $instructions = $run->target->type->instructions();

        /** @var array{reading: Reading, changes: array<int, CreepChange>} $outcome */
        $outcome = DB::transaction(function () use ($run, $instructions, $payload): array {
            $target = $run->target;

            $reading = $instructions->record($run, $payload);

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

            return [
                'reading' => $reading,
                'changes' => array_map(
                    fn (array $attributes): CreepChange => CreepChange::create($attributes),
                    $reading->changes,
                ),
            ];
        });

        // Outside the transaction: a notification must never fire against a
        // reading that later rolls back.
        if ($outcome['changes'] !== [] && $run->target->notify_on_change) {
            $run->target->user->notify(new TargetChanged($run->target, $outcome['changes']));
        }

        return $outcome['reading'];
    }
}
