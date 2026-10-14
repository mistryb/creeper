<?php

namespace App\Jobs;

use App\Actions\DiscoverCompetitors;
use App\Models\CompetitorDiscovery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one competitor discovery, once.
 *
 * One try only: a failure is shown on the dashboard with a button to run it
 * again. A silent retry would spend the user's key a second time without
 * asking.
 */
class RunCompetitorDiscovery implements ShouldBeUnique, ShouldQueue
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

    public function __construct(public CompetitorDiscovery $discovery) {}

    public function uniqueId(): string
    {
        return (string) $this->discovery->id;
    }

    public function handle(DiscoverCompetitors $discover): void
    {
        $discover->handle($this->discovery);
    }

    /**
     * Anything the action did not turn into a sentence of its own — a dropped
     * connection, a timeout — still has to leave the run finished, or the
     * screen would show it running forever.
     */
    public function failed(?Throwable $exception): void
    {
        $discovery = $this->discovery->fresh();

        if ($discovery instanceof CompetitorDiscovery && ! $discovery->status->isFinished()) {
            $discovery->fail('Finding competitors stopped unexpectedly. Try again.');
        }

        Log::warning('Competitor discovery failed.', [
            'competitor_discovery_id' => $this->discovery->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
