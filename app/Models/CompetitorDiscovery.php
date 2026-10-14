<?php

namespace App\Models;

use App\Actions\DiscoverCompetitors;
use App\Concerns\TracksRunStatus;
use App\Enums\RunStatus;
use Database\Factories\CompetitorDiscoveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to find a company's competitors.
 *
 * Created as Queued the moment it is asked for, so the screen can show the
 * request straight away; {@see DiscoverCompetitors} fills the rest in. The
 * report keeps what the model proposed and which of those were added, so a
 * user can see why a name did or did not make the list.
 *
 * @phpstan-type Proposal array{name: string, url: string|null, reason: string, added: bool}
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $api_key_id The key that paid. Null once that key was deleted.
 * @property RunStatus $status
 * @property array{proposals: list<Proposal>, notes: string|null}|null $report
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
class CompetitorDiscovery extends Model
{
    /** @use HasFactory<CompetitorDiscoveryFactory> */
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
}
