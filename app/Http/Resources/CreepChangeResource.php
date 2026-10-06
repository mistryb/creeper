<?php

namespace App\Http\Resources;

use App\Models\CreepChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreepChange
 */
class CreepChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'description' => $this->describe(),
            'detected_at' => $this->detected_at->toIso8601String(),
            'watched_page' => WatchedPageResource::make($this->whenLoaded('watchedPage')),
        ];
    }
}
