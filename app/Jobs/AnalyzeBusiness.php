<?php

namespace App\Jobs;

use App\Actions\RunBusinessAnalysis;
use App\Models\BusinessAnalysis;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one business analysis, once.
 *
 * One try only: the run is started by somebody watching the screen, and a
 * failure is shown to them with a button to run it again. A silent retry would
 * spend their key a second time without asking.
 */
class AnalyzeBusiness implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Comfortably more than the model call's own timeout plus the website
     * fetch. `retry_after` in config/queue.php must stay above this, or a slow
     * run is handed to a second worker and paid for twice.
     */
    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public BusinessAnalysis $analysis) {}

    public function uniqueId(): string
    {
        return (string) $this->analysis->id;
    }

    public function handle(RunBusinessAnalysis $run): void
    {
        $run->handle($this->analysis);
    }

    /**
     * Anything the action did not turn into a sentence of its own — a dropped
     * connection, a timeout — still has to leave the run finished, or the
     * screen would show it running forever.
     */
    public function failed(?Throwable $exception): void
    {
        $analysis = $this->analysis->fresh();

        if ($analysis instanceof BusinessAnalysis && ! $analysis->status->isFinished()) {
            $analysis->fail('The analysis stopped unexpectedly. Run it again.');
        }

        Log::warning('Business analysis failed.', [
            'business_analysis_id' => $this->analysis->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
