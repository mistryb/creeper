<?php

namespace App\Models;

use App\Ai\Contracts\Analyzable;
use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A company a business competes with: who they are, the pages of theirs that
 * Creeper watches, and the analyses run on them.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $url Their website.
 * @property string|null $description What the user knows about them.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read Collection<int, WatchedPage> $watchedPages
 * @property-read Collection<int, BusinessAnalysis> $analyses
 */
#[Fillable(['name', 'url', 'description'])]
class Competitor extends Model implements Analyzable
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    /**
     * Analyses hang off a polymorphic key, which no foreign key can cascade
     * from, so they are taken down here instead.
     */
    protected static function booted(): void
    {
        static::deleting(function (Competitor $competitor): void {
            $competitor->analyses()->delete();
        });
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return HasMany<WatchedPage, $this> */
    public function watchedPages(): HasMany
    {
        return $this->hasMany(WatchedPage::class);
    }

    /** @return MorphMany<BusinessAnalysis, $this> */
    public function analyses(): MorphMany
    {
        return $this->morphMany(BusinessAnalysis::class, 'analyzable')->orderBy('id');
    }

    public function owner(): User
    {
        return $this->business->user;
    }

    /**
     * The competitor, and the business it competes with. A competitor is only
     * interesting relative to somebody, so the model is told who.
     */
    public function analysisBrief(): string
    {
        return implode("\n", array_filter([
            "Name: {$this->name}",
            $this->url === null ? null : "Website: {$this->url}",
            "Relationship: a competitor of the user's business, {$this->business->name}.",
            '',
            'What the user knows about them:',
            filled($this->description) ? $this->description : '(nothing written yet)',
            '',
            "The user's own business, for context — analyse the competitor, not this:",
            $this->business->description,
        ], fn (?string $line): bool => $line !== null));
    }

    public function analysisUrl(): ?string
    {
        return $this->url;
    }
}
