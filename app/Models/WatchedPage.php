<?php

namespace App\Models;

use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Enums\PageStatus;
use Database\Factories\WatchedPageFactory;
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
 * One of a competitor's pages that Creeper watches — a product, a pricing
 * page, a changelog — plus how often and what to do about it.
 *
 * @property int $id
 * @property int $competitor_id
 * @property int|null $api_key_id The key this page spends. Null once the key it used was deleted.
 * @property string $url
 * @property string $watch_for What the user wants watched on the page, in their own words.
 * @property PageCategory $category What kind of news the page carries.
 * @property string|null $name
 * @property PageStatus $status
 * @property CreepFrequency $frequency
 * @property bool $notify_on_change
 * @property int $consecutive_failures
 * @property Carbon|null $last_crept_at
 * @property Carbon|null $next_creep_at
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Competitor $competitor
 * @property-read ApiKey|null $apiKey
 * @property-read Collection<int, CreepRun> $runs
 * @property-read Collection<int, PageSnapshot> $snapshots
 * @property-read Collection<int, CreepChange> $changes
 * @property-read PageSnapshot|null $latestSnapshot
 * @property-read CreepRun|null $latestRun
 */
#[Fillable(['api_key_id', 'url', 'watch_for', 'category', 'name', 'status', 'frequency', 'notify_on_change', 'settings'])]
class WatchedPage extends Model
{
    /** @use HasFactory<WatchedPageFactory> */
    use HasFactory;

    /**
     * A page is parked after this many failures in a row, so a dead URL
     * stops burning agent budget forever.
     */
    public const FAILURE_LIMIT = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PageCategory::class,
            'status' => PageStatus::class,
            'frequency' => CreepFrequency::class,
            'notify_on_change' => 'boolean',
            'consecutive_failures' => 'integer',
            'last_crept_at' => 'datetime',
            'next_creep_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Competitor, $this> */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    /**
     * The account this page is watched for: whoever owns the business the
     * competitor belongs to. They are the one told when it changes.
     */
    public function owner(): User
    {
        return $this->competitor->business->user;
    }

    /**
     * The key this page is crept with. Nullable: deleting a key leaves the
     * pages that used it behind, waiting to be pointed at another one.
     *
     * @return BelongsTo<ApiKey, $this>
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /** @return HasMany<CreepRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(CreepRun::class);
    }

    /**
     * Every reading of this page, one per successful run.
     *
     * @return HasMany<PageSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(PageSnapshot::class);
    }

    /** @return HasMany<CreepChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(CreepChange::class);
    }

    /** @return HasOne<PageSnapshot, $this> */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(PageSnapshot::class)->latestOfMany('captured_at');
    }

    /** @return HasOne<CreepRun, $this> */
    public function latestRun(): HasOne
    {
        return $this->hasOne(CreepRun::class)->latestOfMany();
    }

    /**
     * Pages the scheduler should dispatch right now.
     *
     * @param  Builder<WatchedPage>  $query
     */
    public function scopeDue(Builder $query, ?Carbon $asOf = null): void
    {
        $query->where('status', PageStatus::Active)
            ->whereNotNull('next_creep_at')
            ->where('next_creep_at', '<=', $asOf ?? Carbon::now());
    }

    /**
     * What to call this page in the UI when the user didn't name it.
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
            'next_creep_at' => $this->status === PageStatus::Active
                ? $this->frequency->nextRunAfter($moment)
                : null,
        ])->save();
    }

    /**
     * Stop creeping this page, keeping everything already found.
     *
     * Clearing `next_creep_at` is what actually stops the sweep: {@see scopeDue}
     * matches on it, so a paused page becomes invisible to the scheduler
     * rather than being fetched and thrown away.
     */
    public function pause(): void
    {
        $this->forceFill([
            'status' => PageStatus::Paused,
            'next_creep_at' => null,
        ])->save();
    }

    /**
     * Start creeping again, on whatever schedule the page already has.
     *
     * This is also how a parked page is revived, so the failure streak is
     * cleared here — otherwise the next single failure would park it again.
     */
    public function resume(): void
    {
        $this->forceFill([
            'status' => PageStatus::Active,
            'consecutive_failures' => 0,
            'next_creep_at' => $this->frequency->nextRunAfter($this->last_crept_at ?? Carbon::now()),
        ])->save();
    }
}
