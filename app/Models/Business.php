<?php

namespace App\Models;

use App\Ai\Contracts\Analyzable;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The user's own business — the one whose competitors Creeper watches.
 *
 * The description is written by the owner and is the yardstick every
 * competitor's changes are measured against, so it is free text rather than
 * a set of fields.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $url The business's own website.
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, BusinessAnalysis> $analyses
 * @property-read Collection<int, Competitor> $competitors
 * @property-read Collection<int, LandscapeAnalysis> $landscapes
 */
#[Fillable(['name', 'url', 'description'])]
class Business extends Model implements Analyzable
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * Analyses hang off a polymorphic key, which no foreign key can cascade
     * from. The business's competitors go by database cascade, which fires no
     * model events, so their analyses are taken down here too.
     */
    protected static function booted(): void
    {
        static::deleting(function (Business $business): void {
            BusinessAnalysis::query()
                ->whereMorphedTo('analyzable', $business)
                ->orWhereHasMorph('analyzable', [Competitor::class], fn ($query) => $query->where('business_id', $business->id))
                ->delete();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The companies this business competes with, in the order they were added.
     *
     * @return HasMany<Competitor, $this>
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class)->orderBy('id');
    }

    /**
     * Every landscape run, oldest first.
     *
     * @return HasMany<LandscapeAnalysis, $this>
     */
    public function landscapes(): HasMany
    {
        return $this->hasMany(LandscapeAnalysis::class)->orderBy('id');
    }

    /** @return MorphMany<BusinessAnalysis, $this> */
    public function analyses(): MorphMany
    {
        return $this->morphMany(BusinessAnalysis::class, 'analyzable')->orderBy('id');
    }

    public function owner(): User
    {
        return $this->user;
    }

    public function analysisBrief(): string
    {
        return implode("\n", array_filter([
            "Name: {$this->name}",
            $this->url === null ? null : "Website: {$this->url}",
            'Relationship: this is the user\'s own business.',
            '',
            'How the owner describes it:',
            $this->description,
        ], fn (?string $line): bool => $line !== null));
    }

    public function analysisUrl(): ?string
    {
        return $this->url;
    }
}
