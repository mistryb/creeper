<?php

namespace App\Concerns;

use App\Enums\RunStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The life of a model run somebody started from the screen: queued, running,
 * then succeeded with a report or failed with a reason. Shared by every
 * analysis kept as one row per run.
 *
 * The model casts `status` to {@see RunStatus} and `report` to an array.
 */
trait TracksRunStatus
{
    /**
     * Runs that have been asked for and have not finished.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInFlight(Builder $query): void
    {
        $query->whereIn('status', [RunStatus::Queued, RunStatus::Running]);
    }

    public function markRunning(): void
    {
        $this->forceFill([
            'status' => RunStatus::Running,
            'started_at' => Carbon::now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array{provider?: string|null, model?: string|null, prompt_tokens?: int|null, completion_tokens?: int|null, source_url?: string|null}  $details
     */
    public function succeed(array $report, array $details = []): void
    {
        $this->forceFill([
            ...$details,
            'status' => RunStatus::Succeeded,
            'report' => $report,
            'error' => null,
            'finished_at' => Carbon::now(),
        ])->save();
    }

    public function fail(string $error): void
    {
        $this->forceFill([
            'status' => RunStatus::Failed,
            'error' => $error,
            'finished_at' => Carbon::now(),
        ])->save();
    }
}
