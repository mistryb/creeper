<?php

namespace App\Models;

use App\Enums\CreepFrequency;
use App\Enums\CreepType;
use App\Enums\TargetStatus;
use Database\Factories\CreepTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A URL the user wants creeped, plus how often and what to do about it.
 *
 * @property int $id
 * @property int $user_id
 * @property CreepType $type
 * @property string $url
 * @property string|null $name
 * @property TargetStatus $status
 * @property CreepFrequency $frequency
 * @property bool $notify_on_change
 * @property int $consecutive_failures
 * @property Carbon|null $last_crept_at
 * @property Carbon|null $next_creep_at
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, CreepRun> $runs
 * @property-read Collection<int, ProductSnapshot> $snapshots
 * @property-read Collection<int, CreepChange> $changes
 * @property-read ProductSnapshot|null $latestSnapshot
 * @property-read CreepRun|null $latestRun
 */
#[Fillable(['type', 'url', 'name', 'status', 'frequency', 'notify_on_change', 'settings'])]
class CreepTarget extends Model
{
    /** @use HasFactory<CreepTargetFactory> */
    use HasFactory;

    /**
     * A target is parked after this many failures in a row, so a dead URL
     * stops burning agent budget forever.
     */
    public const FAILURE_LIMIT = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreepType::class,
            'status' => TargetStatus::class,
            'frequency' => CreepFrequency::class,
            'notify_on_change' => 'boolean',
            'consecutive_failures' => 'integer',
            'last_crept_at' => 'datetime',
            'next_creep_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CreepRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(CreepRun::class);
    }

    /** @return HasMany<ProductSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ProductSnapshot::class);
    }

    /** @return HasMany<CreepChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(CreepChange::class);
    }

    /** @return HasOne<ProductSnapshot, $this> */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(ProductSnapshot::class)->latestOfMany('captured_at');
    }

    /** @return HasOne<CreepRun, $this> */
    public function latestRun(): HasOne
    {
        return $this->hasOne(CreepRun::class)->latestOfMany();
    }

    /**
     * Targets the scheduler should dispatch right now.
     *
     * @param  Builder<CreepTarget>  $query
     */
    public function scopeDue(Builder $query, ?Carbon $asOf = null): void
    {
        $query->where('status', TargetStatus::Active)
            ->whereNotNull('next_creep_at')
            ->where('next_creep_at', '<=', $asOf ?? Carbon::now());
    }

    /**
     * What to call this target in the UI when the user didn't name it.
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
    }

    /**
     * Move the schedule forward after a run, whatever its outcome.
     */
    public function rescheduleFrom(Carbon $moment): void
    {
        $this->forceFill([
            'last_crept_at' => $moment,
            'next_creep_at' => $this->status === TargetStatus::Active
                ? $this->frequency->nextRunAfter($moment)
                : null,
        ])->save();
    }
}
