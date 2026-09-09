<?php

namespace App\Models;

use Database\Factories\ChangelogSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What a changelog page said, the last time a run read it.
 *
 * A whole page per row rather than a row per release: the page is what was
 * observed, and a release that quietly changes its own notes — as they do —
 * shows up as a difference between two readings rather than being overwritten.
 *
 * @phpstan-type Feature array{title: string, description: string|null, kind: string}
 * @phpstan-type Release array{version: string|null, released_on: string|null, title: string|null, summary: string|null, features: array<int, Feature>}
 *
 * @property int $id
 * @property int $creep_run_id
 * @property int $creep_target_id
 * @property string|null $product
 * @property string|null $latest_version
 * @property Carbon|null $latest_released_on
 * @property int $release_count
 * @property int $feature_count
 * @property array<int, Release> $releases
 * @property array<string, mixed>|null $extra
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CreepTarget $target
 * @property-read CreepRun $run
 */
#[Fillable([
    'creep_run_id', 'creep_target_id', 'product', 'latest_version', 'latest_released_on',
    'release_count', 'feature_count', 'releases', 'extra', 'captured_at',
])]
class ChangelogSnapshot extends Model
{
    /** @use HasFactory<ChangelogSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latest_released_on' => 'date',
            'release_count' => 'integer',
            'feature_count' => 'integer',
            'releases' => 'array',
            'extra' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CreepTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CreepTarget::class, 'creep_target_id');
    }

    /** @return BelongsTo<CreepRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CreepRun::class, 'creep_run_id');
    }

    /**
     * How a release is recognised again on the next reading.
     *
     * A version is the identity when there is one. Failing that a date will
     * do, and failing that the title — a changelog that gives an entry none of
     * the three is one nobody can track, and the payload drops it.
     *
     * @param  array<string, mixed>  $release
     */
    public static function identify(array $release): string
    {
        foreach (['version', 'released_on', 'title'] as $key) {
            $value = $release[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $key.':'.mb_strtolower(trim($value));
            }
        }

        return '';
    }

    /**
     * The releases on this reading, keyed by identity.
     *
     * @return array<string, array<string, mixed>>
     */
    public function releasesByIdentity(): array
    {
        $keyed = [];

        foreach ($this->releases as $release) {
            $identity = self::identify($release);

            if ($identity !== '') {
                $keyed[$identity] = $release;
            }
        }

        return $keyed;
    }

    /**
     * A one-line description of a release, for the change log and e-mails.
     *
     * @param  array<string, mixed>  $release
     */
    public static function describeRelease(array $release): string
    {
        $version = $release['version'] ?? null;
        $title = $release['title'] ?? null;

        $name = is_string($version) && $version !== ''
            ? $version
            : (is_string($title) && $title !== '' ? $title : 'a new release');

        /** @var array<int, array<string, mixed>> $features */
        $features = is_array($release['features'] ?? null) ? $release['features'] : [];

        $titles = array_values(array_filter(array_map(
            fn (array $feature): ?string => is_string($feature['title'] ?? null) ? $feature['title'] : null,
            $features,
        )));

        if ($titles === []) {
            return $name;
        }

        $listed = array_slice($titles, 0, 3);
        $remaining = count($titles) - count($listed);

        return $name.': '.implode(', ', $listed)
            .($remaining > 0 ? sprintf(' and %d more', $remaining) : '');
    }
}
