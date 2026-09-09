<?php

namespace App\Models;

use App\Enums\ChangeDirection;
use Database\Factories\CreepChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single field that moved between two consecutive snapshots.
 *
 * @property int $id
 * @property int $creep_target_id
 * @property int $from_snapshot_id
 * @property int $to_snapshot_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property ChangeDirection $direction
 * @property Carbon $detected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CreepTarget $target
 */
#[Fillable([
    'creep_target_id', 'from_snapshot_id', 'to_snapshot_id',
    'field', 'old_value', 'new_value', 'direction', 'detected_at',
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
            'direction' => ChangeDirection::class,
            'detected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CreepTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CreepTarget::class, 'creep_target_id');
    }

    /**
     * A one-line description of the change, used in notifications.
     */
    public function describe(): string
    {
        return match ($this->field) {
            'release' => sprintf('Shipped %s', $this->new_value ?? 'a new release'),
            'price' => sprintf('Price %s from %s to %s', $this->direction->value === 'down' ? 'dropped' : 'rose', $this->old_value ?? 'unknown', $this->new_value ?? 'unknown'),
            'availability' => sprintf('Availability changed from %s to %s', $this->old_value ?? 'unknown', $this->new_value ?? 'unknown'),
            default => sprintf('%s changed from %s to %s', ucfirst(str_replace('_', ' ', $this->field)), $this->old_value ?? 'unknown', $this->new_value ?? 'unknown'),
        };
    }
}
