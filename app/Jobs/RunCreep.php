<?php

namespace App\Jobs;

use App\Actions\CompleteCreepRun;
use App\Creeping\CreepManager;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\CreepOutcome;
use App\Enums\RunStatus;
use App\Enums\TargetStatus;
use App\Models\CreepRun;
use App\Models\CreepTarget;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creeps one target, once.
 *
 * Each attempt gets its own {@see CreepRun} row, so a target that succeeded
 * on the third try shows all three attempts in its history rather than
 * quietly overwriting the failures.
 */
class RunCreep implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Long enough for a slow agent, shorter than `retry_after` in config/queue.php.
     */
    public int $timeout = 300;

    /**
     * One target can only be crept once at a time. Long enough to cover a
     * full run plus every backoff step.
     */
    public int $uniqueFor = 3600;

    public function __construct(public CreepTarget $target) {}

    public function uniqueId(): string
    {
        return (string) $this->target->id;
    }

    public function tries(): int
    {
        return (int) config('creeping.retries', 3);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        /** @var array<int, int> $backoff */
        $backoff = config('creeping.backoff', [60, 300, 900]);

        return $backoff;
    }

    public function handle(CreepManager $creeper, CompleteCreepRun $completeRun): void
    {
        $driver = $creeper->driver();

        /** @var CreepRun $run */
        $run = $this->target->runs()->create([
            'status' => RunStatus::Running,
            'driver' => $driver->name(),
            'started_at' => Carbon::now(),
        ]);

        try {
            $result = $driver->creep($run);
        } catch (Throwable $exception) {
            // Transient: record the attempt and let the queue try again.
            $run->finish(RunStatus::Failed, $exception->getMessage());

            throw $exception;
        }

        match ($result->outcome) {
            CreepOutcome::Succeeded => $this->complete($completeRun, $run, $result->payload),
            CreepOutcome::Pending => $this->awaitCallback(),
            CreepOutcome::Failed => $this->recordFailure($run, $result->error ?? 'The creep failed for an unknown reason.'),
        };
    }

    /**
     * Every attempt has been used up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->penaliseTarget();

        Log::warning('Creep failed for target.', [
            'creep_target_id' => $this->target->id,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function complete(CompleteCreepRun $completeRun, CreepRun $run, array $payload): void
    {
        try {
            $completeRun->handle($run, $payload);
        } catch (InvalidCreepPayload $exception) {
            // The agent answered, but with something we can't store. Retrying
            // a malformed response rarely helps, so stop here.
            $this->recordFailure($run, $exception->getMessage());
        }
    }

    /**
     * The agent will post to the run's signed callback when it finishes. Move
     * the schedule on so the sweeper doesn't queue the same target again.
     */
    protected function awaitCallback(): void
    {
        $this->target->rescheduleFrom(Carbon::now());
    }

    /**
     * A definite failure — no retry, no exception.
     */
    protected function recordFailure(CreepRun $run, string $error): void
    {
        $run->finish(RunStatus::Failed, $error);

        $this->penaliseTarget();
    }

    /**
     * Count the failure and park the target once it's clearly dead, so a
     * broken URL stops burning agent budget forever.
     */
    protected function penaliseTarget(): void
    {
        $target = $this->target->fresh();

        if (! $target instanceof CreepTarget) {
            return;
        }

        $failures = $target->consecutive_failures + 1;

        $target->forceFill([
            'consecutive_failures' => $failures,
            'status' => $failures >= CreepTarget::FAILURE_LIMIT
                ? TargetStatus::Failed
                : $target->status,
        ])->save();

        $target->rescheduleFrom(Carbon::now());
    }
}
