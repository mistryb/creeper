<?php

namespace App\Http\Resources;

use App\Models\WatchedPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WatchedPage
 */
class WatchedPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'competitor_id' => $this->competitor_id,
            'competitor' => CompetitorResource::make($this->whenLoaded('competitor')),
            'url' => $this->url,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'watch_for' => $this->watch_for,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'notify_on_change' => $this->notify_on_change,
            // Which key pays for this page. Null once that key was deleted,
            // which is the settings form's cue to ask for another.
            'api_key_id' => $this->api_key_id === null ? null : (string) $this->api_key_id,
            'api_key_label' => $this->whenLoaded('apiKey', fn (): ?string => $this->apiKey?->label()),
            'consecutive_failures' => $this->consecutive_failures,
            'last_crept_at' => $this->last_crept_at?->toIso8601String(),
            'next_creep_at' => $this->next_creep_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'latest_snapshot' => PageSnapshotResource::make($this->whenLoaded('latestSnapshot')),
            'latest_run' => CreepRunResource::make($this->whenLoaded('latestRun')),
        ];
    }
}
