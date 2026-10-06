<?php

namespace App\Console\Commands;

use App\Jobs\RunCreep;
use App\Models\WatchedPage;
use Illuminate\Console\Command;

/**
 * Queues every page whose next creep is due.
 *
 * Runs every minute. `RunCreep` is unique per page, so a page still being
 * crept from a previous sweep is simply skipped.
 */
class DispatchDueCreeps extends Command
{
    protected $signature = 'creeper:dispatch-due';

    protected $description = 'Dispatch a creep for every page that is due';

    public function handle(): int
    {
        $dispatched = 0;

        WatchedPage::query()
            ->due()
            ->chunkById(100, function ($watchedPages) use (&$dispatched): void {
                foreach ($watchedPages as $watchedPage) {
                    RunCreep::dispatch($watchedPage);
                    $dispatched++;
                }
            });

        $this->components->info($dispatched === 1
            ? 'Dispatched 1 creep.'
            : "Dispatched {$dispatched} creeps.");

        return self::SUCCESS;
    }
}
