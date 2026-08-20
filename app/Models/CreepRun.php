<?php

namespace App\Models;

use App\Enums\RunStatus;
use Database\Factories\CreepRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One attempt to creep a target.
 *
 * @property int $id
 * @property int $creep_target_id
 * @property RunStatus $status
 * @property string $driver
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property string|null $error
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CreepTarget $target
 * @property-read ProductSnapshot|null $snapshot
 */
#[Fillable(['creep_target_id', 'status', 'driver', 'started_at', 'finished_at', 'duration_ms', 'error', 'raw_payload'])]
class CreepRun extends Model
{
    /** @use HasFactory<CreepRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<CreepTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CreepTarget::class, 'creep_target_id');
    }

    /** @return HasOne<ProductSnapshot, $this> */
    public function snapshot(): HasOne
    {
        return $this->hasOne(ProductSnapshot::class);
    }

    /**
     * Stamp the run as finished and record how long it took.
     */
    public function finish(RunStatus $status, ?string $error = null): void
    {
        $finishedAt = Carbon::now();

        $this->forceFill([
            'status' => $status,
            'error' => $error,
            'finished_at' => $finishedAt,
            'duration_ms' => $this->started_at
                ? (int) $this->started_at->diffInMilliseconds($finishedAt, absolute: true)
                : null,
        ])->save();
    }
}
