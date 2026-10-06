<?php

namespace App\Creeping;

use App\Ai\Agents\WatchedPageAgent;
use App\Creeping\Data\PagePayload;
use App\Creeping\Data\Reading;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Creeping\Fetching\DigestProfile;
use App\Creeping\Fetching\PageDigest;
use App\Models\CreepRun;
use App\Models\PageSnapshot;
use App\Models\WatchedPage;

/**
 * How a watched page is read, end to end: what the reader is shown, what it is
 * asked, where the reading is filed, and what counts as news.
 *
 * Every page is read the same way. What differs is the user's own description
 * of what to watch for, which travels in the prompt.
 */
class WatchInstructions
{
    public function __construct(private FactDetector $detector = new FactDetector) {}

    public function agent(): WatchedPageAgent
    {
        return new WatchedPageAgent;
    }

    /**
     * Boil the page down to what the reader should pay to read.
     */
    public function digest(string $html, int $maxCharacters): PageDigest
    {
        return PageDigest::fromHtml($html, $maxCharacters, DigestProfile::page());
    }

    /**
     * The whole prompt: what to watch for, the labels used last time so they
     * can be reused, then the page.
     */
    public function prompt(WatchedPage $watchedPage, PageDigest $digest, string $url): string
    {
        $labels = array_column($watchedPage->latestSnapshot?->facts ?? [], 'label');

        return implode("\n\n", array_filter([
            "# What to watch for\n".$watchedPage->watch_for,
            $labels === []
                ? null
                : "# Labels used last time\nReuse these exactly for the same things:\n- ".implode("\n- ", $labels),
            "# The page\n".$digest->toPrompt($url),
        ]));
    }

    /**
     * What to tell the user when nothing readable came back.
     */
    public function unreadable(string $url): string
    {
        return "There was nothing readable at [{$url}]. Pages that build themselves in the browser, or sit behind a login, are invisible to this driver.";
    }

    /**
     * File a reading against the run, and say what it changed.
     *
     * Runs inside the completing transaction, so it may read the page's
     * previous reading and must not reach outside the database.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidCreepPayload
     */
    public function record(CreepRun $run, array $payload): Reading
    {
        $reading = PagePayload::fromArray($payload);

        $previous = $run->watchedPage->snapshots()->latest('captured_at')->latest('id')->first();

        /** @var PageSnapshot $snapshot */
        $snapshot = $run->snapshot()->create([
            ...$reading->toSnapshotAttributes(),
            'watched_page_id' => $run->watched_page_id,
        ]);

        return new Reading(
            $snapshot,
            $previous instanceof PageSnapshot ? $this->detector->compare($previous, $snapshot) : [],
        );
    }
}
