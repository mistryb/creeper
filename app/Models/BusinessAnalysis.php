<?php

namespace App\Models;

use App\Actions\RunBusinessAnalysis;
use App\Ai\Contracts\Analyzable;
use App\Concerns\TracksRunStatus;
use App\Enums\RunStatus;
use Database\Factories\BusinessAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One run of the business analysis, on a business or a competitor.
 *
 * Created as Queued the moment somebody asks for it, so the screen can show
 * the request straight away; {@see RunBusinessAnalysis} fills the
 * rest in.
 *
 * @property int $id
 * @property string $analyzable_type
 * @property int $analyzable_id
 * @property int|null $api_key_id The key that paid. Null once that key was deleted.
 * @property RunStatus $status
 * @property array{summary: string, offering: string, audience: string, positioning: string, pricing: string|null, strengths: list<string>, weaknesses: list<string>, opportunities: list<string>, threats: list<string>, confidence: string, notes: string|null}|null $report
 * @property string|null $source_url The website that was read, if any.
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model&Analyzable $analyzable
 * @property-read ApiKey|null $apiKey
 */
class BusinessAnalysis extends Model
{
    /** @use HasFactory<BusinessAnalysisFactory> */
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
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function analyzable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }
}
