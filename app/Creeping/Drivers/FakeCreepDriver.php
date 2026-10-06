<?php

namespace App\Creeping\Drivers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Data\CreepResult;
use App\Models\CreepRun;

/**
 * A driver that invents plausible data without leaving the machine.
 *
 * This is what a fresh clone runs on, and what the test suite uses. It answers
 * with a summary and a handful of facts, and they move between runs — a price
 * drifts, a release ships — so change detection has something to find.
 */
class FakeCreepDriver implements CreepDriver
{
    /**
     * Payloads queued up by tests, returned in order before falling back to
     * generated data.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $queue = [];

    protected ?string $failure = null;

    public function name(): string
    {
        return 'fake';
    }

    public function creep(CreepRun $run): CreepResult
    {
        if ($this->failure !== null) {
            return CreepResult::failed($this->failure);
        }

        if ($this->queue !== []) {
            return CreepResult::succeeded(array_shift($this->queue));
        }

        return CreepResult::succeeded($this->invent($run));
    }

    /**
     * Queue an exact payload for the next creep.
     *
     * @param  array<string, mixed>  $payload
     */
    public function willReturn(array $payload): self
    {
        $this->queue[] = $payload;

        return $this;
    }

    /**
     * Make every subsequent creep report a definite failure.
     */
    public function willFail(string $error = 'The fake driver was told to fail.'): self
    {
        $this->failure = $error;

        return $this;
    }

    /**
     * Invent a reading of this page.
     *
     * A pricing table whose middle plan drifts and whose release number walks
     * forward with every run, so a second creep always finds something that
     * moved — which is what change detection is for.
     *
     * @return array<string, mixed>
     */
    protected function invent(CreepRun $run): array
    {
        $seed = crc32($run->watchedPage->url);
        $runs = $run->watchedPage->runs()->count();
        $price = 10 + ($seed % 40) + random_int(0, 3) * 5;

        return [
            'summary' => sprintf(
                'Three plans, with Pro at $%d a month. The latest release is v2.%d.0.',
                $price,
                $runs,
            ),
            'facts' => [
                ['label' => 'Starter plan', 'value' => 'Free'],
                ['label' => 'Pro plan', 'value' => "\${$price}/month"],
                ['label' => 'Enterprise plan', 'value' => 'Contact sales'],
                ['label' => 'Latest release', 'value' => "v2.{$runs}.0"],
            ],
        ];
    }
}
