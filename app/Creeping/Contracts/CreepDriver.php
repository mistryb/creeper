<?php

namespace App\Creeping\Contracts;

use App\Creeping\Data\CreepResult;
use App\Models\CreepRun;

/**
 * The seam between Creeper and whatever actually does the creeping.
 *
 * Implement this to plug in your own agent. Creeper handles targets,
 * scheduling, history, change detection and the UI; a driver only has to
 * turn a URL into product data.
 */
interface CreepDriver
{
    /**
     * The name recorded against every run this driver performs.
     */
    public function name(): string;

    /**
     * Creep the run's target.
     *
     * Return {@see CreepResult::succeeded()} with the product data, or
     * {@see CreepResult::pending()} if the work is asynchronous and the
     * agent will post to the run's signed callback URL when it finishes.
     *
     * Throw for transient problems — a dropped connection, an agent that is
     * briefly down. The queue retries those. Return
     * {@see CreepResult::failed()} only when the target genuinely cannot be
     * crept, so the run stops rather than retrying against a dead page.
     */
    public function creep(CreepRun $run): CreepResult;
}
