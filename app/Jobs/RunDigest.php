<?php

namespace App\Jobs;

use App\Actions\BuildDigest;
use App\Models\Digest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes and sends one digest, once.
 *
 * One try only: a failed digest is shown on the dashboard with its reason and
 * a button to send again, and the schedule has already moved on. A silent
 * retry would spend the user's key a second time without asking.
 */
class RunDigest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Comfortably more than the model call's own timeout. `retry_after` in
     * config/queue.php must stay above this, or a slow run is handed to a
     * second worker and paid for — and emailed — twice.
     */
    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public Digest $digest) {}

    public function uniqueId(): string
    {
        return (string) $this->digest->id;
    }

    public function handle(BuildDigest $build): void
    {
        $build->handle($this->digest);
    }

    /**
     * Anything the action did not turn into a sentence of its own — a dropped
     * connection, a timeout — still has to leave the run finished, or the
     * screen would show it running forever.
     */
    public function failed(?Throwable $exception): void
    {
        $digest = $this->digest->fresh();

        if ($digest instanceof Digest && ! $digest->status->isFinished()) {
            $digest->fail('The digest stopped unexpectedly. Send it again.');
        }

        Log::warning('Digest failed.', [
            'digest_id' => $this->digest->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
