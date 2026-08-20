<?php

namespace App\Jobs;

use App\Billing\RunMeter;
use App\Models\CreepRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reports one billable run to the Stripe meter.
 *
 * Queued deliberately: a Stripe outage should delay an invoice line, never
 * fail a creep the user already got their results from.
 */
class ReportCreepRunUsage implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(public CreepRun $run) {}

    public function handle(RunMeter $meter): void
    {
        $meter->report($this->run);
    }
}
