<?php

namespace App\Models;

use App\Enums\ChangeKind;
use Database\Factories\CreepChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One fact that appeared, disappeared or changed value between two
 * consecutive readings of a page.
 *
 * @property int $id
 * @property int $watched_page_id
 * @property int $from_snapshot_id
 * @property int $to_snapshot_id
 * @property string $label The fact, as the page names it.
 * @property string|null $old_value
 * @property string|null $new_value
 * @property ChangeKind $kind
 * @property Carbon $detected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WatchedPage $watchedPage
 */
#[Fillable([
    'watched_page_id', 'from_snapshot_id', 'to_snapshot_id',
    'label', 'old_value', 'new_value', 'kind', 'detected_at',
])]
class CreepChange extends Model
{
    /** @use HasFactory<CreepChangeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ChangeKind::class,
            'detected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WatchedPage, $this> */
    public function watchedPage(): BelongsTo
    {
        return $this->belongsTo(WatchedPage::class, 'watched_page_id');
    }

    /**
     * A one-line description of the change, used in notifications.
     */
    public function describe(): string
    {
        return match ($this->kind) {
            ChangeKind::Added => sprintf('New: %s — %s', $this->label, $this->new_value ?? ''),
            ChangeKind::Removed => sprintf('Gone: %s (was %s)', $this->label, $this->old_value ?? 'unknown'),
            ChangeKind::Changed => sprintf('%s: %s → %s', $this->label, $this->old_value ?? 'unknown', $this->new_value ?? 'unknown'),
        };
    }
}
