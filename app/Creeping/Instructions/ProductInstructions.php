<?php

namespace App\Creeping\Instructions;

use App\Ai\Agents\ProductPageAgent;
use App\Creeping\ChangeDetector;
use App\Creeping\Contracts\CreepInstructions;
use App\Creeping\Data\ProductPayload;
use App\Creeping\Data\Reading;
use App\Creeping\Fetching\DigestProfile;
use App\Creeping\Fetching\PageDigest;
use App\Models\CreepRun;
use App\Models\ProductSnapshot;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Creep one product page: what it costs, whether it's in stock, what people
 * make of it.
 */
final class ProductInstructions implements CreepInstructions
{
    public function __construct(private ChangeDetector $detector = new ChangeDetector) {}

    public function agent(): Agent&HasStructuredOutput
    {
        return new ProductPageAgent;
    }

    public function digest(string $html, int $maxCharacters): PageDigest
    {
        return PageDigest::fromHtml($html, $maxCharacters, DigestProfile::product());
    }

    public function unreadable(string $url): string
    {
        return "There was nothing readable at [{$url}]. Pages that build themselves in the browser are invisible to this driver.";
    }

    public function record(CreepRun $run, array $payload): Reading
    {
        $product = ProductPayload::fromArray($payload);

        $target = $run->target;
        $previous = $target->snapshots()->latest('captured_at')->first();

        /** @var ProductSnapshot $snapshot */
        $snapshot = $run->snapshot()->create([
            ...$product->toSnapshotAttributes(),
            'creep_target_id' => $target->id,
        ]);

        return new Reading(
            $snapshot,
            $previous instanceof ProductSnapshot ? $this->detector->compare($previous, $snapshot) : [],
        );
    }
}
