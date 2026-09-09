<?php

namespace App\Creeping;

use App\Enums\ChangeDirection;
use App\Models\ChangelogSnapshot;
use Illuminate\Support\Carbon;

/**
 * Works out what shipped between two readings of a changelog.
 *
 * The counterpart to {@see ChangeDetector}: the snapshots keep every release
 * the page listed, and this produces the human-readable log of the ones that
 * weren't there last time.
 *
 * Releases are matched on identity, not position, so a page that grows a
 * banner or reorders itself reports nothing. Only additions are news — a
 * changelog quietly rewording an old entry is not worth an e-mail, and a
 * release falling off the bottom of a paginated page certainly isn't.
 */
class ReleaseDetector
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function compare(ChangelogSnapshot $from, ChangelogSnapshot $to): array
    {
        $detectedAt = $to->captured_at ?? Carbon::now();
        $known = $from->releasesByIdentity();

        $changes = [];

        // Oldest first, so a run that catches up on three releases logs them
        // in the order they shipped.
        foreach (array_reverse($to->releases) as $release) {
            $identity = ChangelogSnapshot::identify($release);

            if ($identity === '' || isset($known[$identity])) {
                continue;
            }

            $changes[] = [
                'field' => 'release',
                'old_value' => $from->latest_version,
                'new_value' => ChangelogSnapshot::describeRelease($release),
                'direction' => ChangeDirection::Changed,
            ];
        }

        return array_map(fn (array $change): array => [
            ...$change,
            'creep_target_id' => $to->creep_target_id,
            'from_snapshot_id' => $from->id,
            'to_snapshot_id' => $to->id,
            'detected_at' => $detectedAt,
        ], $changes);
    }
}
