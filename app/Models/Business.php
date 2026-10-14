<?php

namespace App\Models;

use App\Enums\DigestFrequency;
use App\Enums\RunStatus;
use Carbon\CarbonInterface;
use Database\Factories\BusinessFactory;
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
 * The user's own company — the one whose competitors Creeper writes about.
 *
 * The description is written by the owner and is the yardstick every
 * competitor is found and read against, so it is free text rather than a set
 * of fields.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $api_key_id The key discovery and digests spend. Null once that key was deleted.
 * @property string $name
 * @property string|null $url The company's own website.
 * @property string $description
 * @property DigestFrequency $digest_frequency
 * @property Carbon|null $last_digest_at
 * @property Carbon|null $next_digest_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ApiKey|null $apiKey
 * @property-read Collection<int, Competitor> $competitors
 * @property-read Collection<int, CompetitorDiscovery> $discoveries
 * @property-read Collection<int, Digest> $digests
 * @property-read Digest|null $latestDigest
 */
#[Fillable(['name', 'url', 'description', 'digest_frequency', 'api_key_id'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'digest_frequency' => DigestFrequency::class,
            'last_digest_at' => 'datetime',
            'next_digest_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * The companies this one competes with, in the order they were added.
     *
     * @return HasMany<Competitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class)->orderBy('id');
    }

    /**
     * Every attempt to find competitors, oldest first.
     *
     * @return HasMany<CompetitorDiscovery, $this>
     */
    public function discoveries(): HasMany
    {
        return $this->hasMany(CompetitorDiscovery::class)->orderBy('id');
    }

    /**
     * Every digest, oldest first.
     *
     * @return HasMany<Digest, $this>
     */
    public function digests(): HasMany
    {
        return $this->hasMany(Digest::class)->orderBy('id');
    }

    /**
     * The newest digest that was actually written and sent.
     *
     * @return HasOne<Digest, $this>
     */
    public function latestDigest(): HasOne
    {
        return $this->hasOne(Digest::class)->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('status', RunStatus::Succeeded));
    }

    /**
     * Companies whose next digest is due.
     *
     * @param  Builder<Business>  $query
     */
    public function scopeDue(Builder $query, ?CarbonInterface $asOf = null): void
    {
        $query->whereNotNull('next_digest_at')
            ->where('next_digest_at', '<=', $asOf ?? Carbon::now());
    }

    /**
     * Where the next digest picks up from: the last one sent, or the day the
     * company was set up when none has gone out yet.
     */
    public function digestPeriodStart(): CarbonInterface
    {
        return $this->last_digest_at ?? $this->created_at ?? Carbon::now();
    }

    /**
     * Put the next digest on the calendar, counting from the given moment.
     */
    public function scheduleDigestFrom(CarbonInterface $moment): void
    {
        $this->forceFill(['next_digest_at' => $this->digest_frequency->nextRunAfter($moment)])->save();
    }

    /**
     * Record that a digest went out, so the next one picks up from here.
     */
    public function markDigestSent(CarbonInterface $moment): void
    {
        $this->forceFill(['last_digest_at' => $moment])->save();
    }

    /**
     * What the company is, in the owner's words, for the model to work from.
     */
    public function brief(): string
    {
        return implode("\n", array_filter([
            "Name: {$this->name}",
            $this->url === null ? null : "Website: {$this->url}",
            '',
            'How the owner describes it:',
            $this->description,
        ], fn (?string $line): bool => $line !== null));
    }

    /**
     * The host of the company's own website, for telling itself apart from a
     * competitor the model proposes. Null when it has no website.
     */
    public function host(): ?string
    {
        if ($this->url === null) {
            return null;
        }

        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) ? mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host) : null;
    }
}
