<?php

namespace App\Creeping\Contracts;

use App\Creeping\Data\Reading;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Creeping\Fetching\PageDigest;
use App\Enums\CreepType;
use App\Models\CreepRun;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * One kind of creeping, end to end.
 *
 * A creeper is only as good as its instructions: which agent reads the page,
 * how much of the page that agent is shown, where the reading is filed, and
 * what counts as news. Everything a type needs to answer differently lives
 * behind this interface, so the shared pipeline — runs, retries, scheduling,
 * notifications — never asks what kind of target it is holding.
 *
 * @see CreepType::instructions()
 */
interface CreepInstructions
{
    /**
     * The agent that reads a page of this kind.
     */
    public function agent(): Agent&HasStructuredOutput;

    /**
     * Boil the page down to what this agent should pay to read.
     */
    public function digest(string $html, int $maxCharacters): PageDigest;

    /**
     * What to tell the user when nothing readable came back.
     */
    public function unreadable(string $url): string;

    /**
     * File an agent's output against the run, and say what it changed.
     *
     * Runs inside the completing transaction, so this may read the target's
     * previous readings and must not reach outside the database.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCreepPayload
     */
    public function record(CreepRun $run, array $payload): Reading;
}
