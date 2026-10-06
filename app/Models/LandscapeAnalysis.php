<?php

namespace App\Models;

use App\Actions\RunLandscapeAnalysis;
use App\Concerns\TracksRunStatus;
use App\Enums\RunStatus;
use Database\Factories\LandscapeAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One run of the landscape: a business set against all its competitors on
 * dimensions that matter in its market.
 *
 * Subjects in the report are `you` for the business and `competitor:{id}` for
 * each competitor, so a row survives a rename and is dropped when its
 * competitor is deleted. See {@see RunLandscapeAnalysis} for the shape.
 *
 * @phpstan-type Dimension array{name: string, kind: 'fact'|'judgement', description: string}
 * @phpstan-type Cell array{dimension: string, value: string, score: int|null}
 * @phpstan-type Row array{subject: string, confidence: 'high'|'medium'|'low', cells: list<Cell>}
 * @phpstan-type Point array{subject: string, x: float, y: float}
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $api_key_id
 * @property RunStatus $status
 * @property array{summary: string, actions: list<string>, dimensions: list<Dimension>, rows: list<Row>, map: array{x_axis: string, y_axis: string, points: list<Point>}}|null $report
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
class LandscapeAnalysis extends Model
{
    /** @use HasFactory<LandscapeAnalysisFactory> */
    use HasFactory, TracksRunStatus;

    /**
     * The subject id the business itself goes by in a report.
     */
    public const YOU = 'you';

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
     * The subject id a competitor goes by in a report.
     */
    public static function subjectFor(Competitor $competitor): string
    {
        return "competitor:{$competitor->id}";
    }
}
