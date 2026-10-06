<?php

namespace App\Actions;

use App\Creeping\Data\Reading;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Creeping\WatchInstructions;
use App\Enums\PageStatus;
use App\Enums\RunStatus;
use App\Models\CreepChange;
use App\Models\CreepRun;
use App\Notifications\PageChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns a successful creep into a reading, a set of changes, and a notification.
 *
 * Both paths into Creeper end up here — the driver returning data inline, and
 * an agent posting to the signed callback later — so there is exactly one
 * place where a run becomes a result.
 *
 * Reading the payload is delegated to {@see WatchInstructions}, which
 * validates it, files the snapshot and says what moved. Everything around it —
 * the transaction, the failure streak, the schedule, the e-mail — is here.
 */
class CompleteCreepRun
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCreepPayload
     */
    public function __construct(private WatchInstructions $instructions) {}

    public function handle(CreepRun $run, array $payload): Reading
    {
        $instructions = $this->instructions;

        /** @var array{reading: Reading, changes: array<int, CreepChange>} $outcome */
        $outcome = DB::transaction(function () use ($run, $instructions, $payload): array {
            $watchedPage = $run->watchedPage;

            $reading = $instructions->record($run, $payload);

            $run->forceFill(['raw_payload' => $payload])->save();
            $run->finish(RunStatus::Succeeded);

            // A success clears the failure streak and revives a parked page.
            $watchedPage->forceFill([
                'consecutive_failures' => 0,
                'status' => $watchedPage->status === PageStatus::Failed
                    ? PageStatus::Active
                    : $watchedPage->status,
            ])->save();

            $watchedPage->rescheduleFrom(Carbon::now());

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
        if ($outcome['changes'] !== [] && $run->watchedPage->notify_on_change) {
            $run->watchedPage->owner()->notify(new PageChanged($run->watchedPage, $outcome['changes']));
        }

        return $outcome['reading'];
    }
}
