<?php

namespace App\Http\Resources;

use App\Models\CreepTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreepTarget
 */
class CreepTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'name' => $this->name,
            'display_name' => $this->displayName(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'notify_on_change' => $this->notify_on_change,
            'consecutive_failures' => $this->consecutive_failures,
            'last_crept_at' => $this->last_crept_at?->toIso8601String(),
            'next_creep_at' => $this->next_creep_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // One of these per type, and only the loaded one is ever sent.
            'latest_snapshot' => ProductSnapshotResource::make($this->whenLoaded('latestSnapshot')),
            'latest_changelog_snapshot' => ChangelogSnapshotResource::make($this->whenLoaded('latestChangelogSnapshot')),
            'latest_run' => CreepRunResource::make($this->whenLoaded('latestRun')),
        ];
    }
}
