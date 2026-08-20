<?php

namespace App\Console\Commands;

use App\Jobs\RunCreep;
use App\Models\CreepTarget;
use Illuminate\Console\Command;

/**
 * Queues every target whose next creep is due.
 *
 * Runs every minute. `RunCreep` is unique per target, so a target still being
 * crept from a previous sweep is simply skipped.
 */
class DispatchDueCreeps extends Command
{
    protected $signature = 'creeper:dispatch-due';

    protected $description = 'Dispatch a creep for every target that is due';

    public function handle(): int
    {
        $dispatched = 0;

        CreepTarget::query()
            ->due()
            ->with('user')
            ->chunkById(100, function ($targets) use (&$dispatched): void {
                foreach ($targets as $target) {
                    RunCreep::dispatch($target);
                    $dispatched++;
                }
            });

        $this->components->info($dispatched === 1
            ? 'Dispatched 1 creep.'
            : "Dispatched {$dispatched} creeps.");

        return self::SUCCESS;
    }
}
