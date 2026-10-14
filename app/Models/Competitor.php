<?php

namespace App\Models;

use Database\Factories\CompetitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A company a business competes with, whether the user named them or Creeper
 * found them.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string|null $url Their website.
 * @property string|null $description What the user knows about them.
 * @property Carbon|null $discovered_at When Creeper found them. Null for ones the user added.
 * @property string|null $reason Why Creeper picked them, in one line.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 */
#[Fillable(['name', 'url', 'description', 'discovered_at', 'reason'])]
class Competitor extends Model
{
    /** @use HasFactory<CompetitorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The account this competitor is written up for: whoever owns the
     * company they compete with.
     */
    public function owner(): User
    {
        return $this->business->user;
    }

    /**
     * How a prompt and a report refer to this competitor, so a row survives
     * a rename and is dropped when the competitor is deleted.
     */
    public function subject(): string
    {
        return "competitor:{$this->id}";
    }

    /**
     * The website's host, lowercased and without `www.`, for matching a
     * proposed competitor against the ones already here. Null without a URL.
     */
    public function host(): ?string
    {
        return self::hostOf($this->url);
    }

    public static function hostOf(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host) : null;
    }
}
