<?php

namespace App\Console\Commands;

use App\Enums\RunStatus;
use App\Jobs\RunDigest;
use App\Models\Business;
use App\Models\Digest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queues a digest for every company whose next one is due.
 *
 * Runs every minute. A company with a digest still in flight is skipped —
 * the one running will move the schedule on when it finishes — and the
 * schedule is advanced as soon as the digest is queued, so a company is never
 * picked up twice while its worker is busy.
 */
class DispatchDueDigests extends Command
{
    protected $signature = 'creeper:dispatch-due-digests';

    protected $description = 'Queue a digest for every company whose next one is due';

    public function handle(): int
    {
        $dispatched = 0;

        Business::query()
            ->due()
            ->whereDoesntHave('digests', fn (Builder $query) => $query->whereIn('status', [RunStatus::Queued, RunStatus::Running]))
            ->chunkById(100, function (Collection $businesses) use (&$dispatched): void {
                foreach ($businesses as $business) {
                    $digest = Digest::queueFor($business);

                    $business->scheduleDigestFrom($digest->period_end);

                    RunDigest::dispatch($digest);
                    $dispatched++;
                }
            });

        $this->components->info($dispatched === 1
            ? 'Queued 1 digest.'
            : "Queued {$dispatched} digests.");

        return self::SUCCESS;
    }
}
