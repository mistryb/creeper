<?php

namespace App\Models;

use App\Actions\BuildDigest;
use App\Concerns\TracksRunStatus;
use App\Enums\RunStatus;
use Carbon\CarbonInterface;
use Database\Factories\DigestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One digest: what every competitor of a company has been doing over a
 * stretch of time, written by a model with the web at hand and emailed to
 * the owner.
 *
 * Competitors in the report go by `competitor:{id}` ({@see Competitor::subject()}),
 * so a section survives a rename and is dropped when its competitor is
 * deleted. See {@see BuildDigest} for the shape.
 *
 * @phpstan-type Item array{title: string, detail: string, url: string|null, date: string|null}
 * @phpstan-type Section array{subject: string, summary: string, items: list<Item>}
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $api_key_id
 * @property RunStatus $status
 * @property array{headline: string, competitors: list<Section>, notes: string|null}|null $report
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $sent_at
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business $business
 * @property-read ApiKey|null $apiKey
 */
class Digest extends Model
{
    /** @use HasFactory<DigestFactory> */
    use HasFactory, TracksRunStatus;

    /**
     * Nothing here is mass assignable: every column is written by the run
     * itself, never from a request.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'report' => 'array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'sent_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Queue a digest for a company covering everything since the last one.
     */
    public static function queueFor(Business $business, ?CarbonInterface $until = null): self
    {
        $until ??= Carbon::now();

        $digest = new self;
        $digest->forceFill([
            'api_key_id' => $business->api_key_id,
            'status' => RunStatus::Queued,
            'period_start' => $business->digestPeriodStart(),
            'period_end' => $until,
        ]);

        $business->digests()->save($digest);

        return $digest;
    }
}
