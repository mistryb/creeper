<?php

namespace App\Jobs;

use App\Actions\RunLandscapeAnalysis;
use App\Models\LandscapeAnalysis;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one landscape, once.
 *
 * One try only, for the same reason as {@see AnalyzeBusiness}: somebody is
 * watching for the result, and a silent retry would spend their key twice.
 */
class RunLandscape implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Comfortably more than the model call's own timeout. `retry_after` in
     * config/queue.php must stay above this.
     */
    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public LandscapeAnalysis $landscape) {}

    public function uniqueId(): string
    {
        return (string) $this->landscape->id;
    }

    public function handle(RunLandscapeAnalysis $run): void
    {
        $run->handle($this->landscape);
    }

    /**
     * Never leave a run looking as if it is still going.
     */
    public function failed(?Throwable $exception): void
    {
        $landscape = $this->landscape->fresh();

        if ($landscape instanceof LandscapeAnalysis && ! $landscape->status->isFinished()) {
            $landscape->fail('The comparison stopped unexpectedly. Run it again.');
        }

        Log::warning('Landscape analysis failed.', [
            'landscape_analysis_id' => $this->landscape->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
