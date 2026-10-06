<?php

namespace App\Models;

use Database\Factories\PageSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reading of a watched page: what it said about the thing being watched,
 * as a summary and a list of named facts.
 *
 * @property int $id
 * @property int $creep_run_id
 * @property int $watched_page_id
 * @property string $summary
 * @property list<array{label: string, value: string}> $facts
 * @property array<string, mixed>|null $extra Whatever else the reader sent, kept verbatim.
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CreepRun $run
 * @property-read WatchedPage $watchedPage
 */
#[Fillable(['creep_run_id', 'watched_page_id', 'summary', 'facts', 'extra', 'captured_at'])]
class PageSnapshot extends Model
{
    /** @use HasFactory<PageSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'extra' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CreepRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CreepRun::class, 'creep_run_id');
    }

    /** @return BelongsTo<WatchedPage, $this> */
    public function watchedPage(): BelongsTo
    {
        return $this->belongsTo(WatchedPage::class);
    }

    /**
     * The facts keyed by their comparison key, so two readings can be lined
     * up fact by fact.
     *
     * @return array<string, array{label: string, value: string}>
     */
    public function factsByKey(): array
    {
        $keyed = [];

        foreach ($this->facts as $fact) {
            $keyed[self::key($fact['label'])] = $fact;
        }

        return $keyed;
    }

    /**
     * How a label is compared: case, spacing and trailing punctuation are not
     * news, so "Pro plan" and "pro plan:" are the same fact.
     */
    public static function key(string $label): string
    {
        return rtrim(mb_strtolower(preg_replace('/\s+/u', ' ', trim($label)) ?? ''), ' :.-');
    }
}
