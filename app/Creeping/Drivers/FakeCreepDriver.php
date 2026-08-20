<?php

namespace App\Creeping\Drivers;

use App\Creeping\Contracts\CreepDriver;
use App\Creeping\Data\CreepResult;
use App\Models\CreepRun;

/**
 * A driver that invents plausible product data without leaving the machine.
 *
 * This is what a fresh clone runs on, and what the test suite uses. Prices
 * drift a little between runs so change detection has something to find.
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
     * Invent a product for this target.
     *
     * The base price is derived from the URL so a target keeps its identity
     * across runs; the jitter is what makes a price history interesting.
     *
     * @return array<string, mixed>
     */
    protected function invent(CreepRun $run): array
    {
        $seed = crc32($run->target->url);
        $basePrice = 1000 + ($seed % 25000);
        $jitter = random_int(-500, 500);

        return [
            'title' => $run->target->name ?? 'Product at '.(parse_url($run->target->url, PHP_URL_HOST) ?: 'unknown host'),
            'brand' => ['Acme', 'Globex', 'Initech', 'Umbrella'][$seed % 4],
            'sku' => 'SKU-'.str_pad((string) ($seed % 100000), 6, '0', STR_PAD_LEFT),
            'price_amount' => max(100, $basePrice + $jitter),
            'currency' => 'GBP',
            'availability' => random_int(1, 10) > 2 ? 'in_stock' : 'out_of_stock',
            'rating' => round(3 + (($seed % 200) / 100), 2),
            'review_count' => $seed % 5000,
            'image_url' => 'https://placehold.co/600x400?text=Creeper',
        ];
    }
}
