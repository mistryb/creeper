<?php

namespace App\Creeping\Instructions;

use App\Ai\Agents\ChangelogPageAgent;
use App\Creeping\Contracts\CreepInstructions;
use App\Creeping\Data\ChangelogPayload;
use App\Creeping\Data\Reading;
use App\Creeping\Fetching\DigestProfile;
use App\Creeping\Fetching\PageDigest;
use App\Creeping\ReleaseDetector;
use App\Models\ChangelogSnapshot;
use App\Models\CreepRun;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * Creep one changelog: which releases a product has shipped, and what each of
 * them added.
 *
 * The same page every time, saying something new every so often — so a reading
 * keeps the whole list and the news is whatever wasn't in the last one.
 */
final class ChangelogInstructions implements CreepInstructions
{
    public function __construct(private ReleaseDetector $detector = new ReleaseDetector) {}

    public function agent(): Agent&HasStructuredOutput
    {
        return new ChangelogPageAgent;
    }

    public function digest(string $html, int $maxCharacters): PageDigest
    {
        return PageDigest::fromHtml($html, $maxCharacters, DigestProfile::changelog());
    }

    public function unreadable(string $url): string
    {
        return "There was nothing readable at [{$url}]. Release notes rendered by JavaScript, or kept behind a login, are invisible to this driver.";
    }

    public function record(CreepRun $run, array $payload): Reading
    {
        $changelog = ChangelogPayload::fromArray($payload);

        $target = $run->target;
        $previous = $target->changelogSnapshots()->latest('captured_at')->first();

        /** @var ChangelogSnapshot $snapshot */
        $snapshot = $run->changelogSnapshot()->create([
            ...$changelog->toSnapshotAttributes(),
            'creep_target_id' => $target->id,
        ]);

        return new Reading(
            $snapshot,
            $previous instanceof ChangelogSnapshot ? $this->detector->compare($previous, $snapshot) : [],
        );
    }
}
