<?php

namespace App\Http\Resources;

use App\Models\Digest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Digest
 */
class DigestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'report' => $this->report,
            'period_start' => $this->period_start->toIso8601String(),
            'period_end' => $this->period_end->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
