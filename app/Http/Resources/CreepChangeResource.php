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
            'field' => $this->field,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'direction' => $this->direction->value,
            'description' => $this->describe(),
            'detected_at' => $this->detected_at->toIso8601String(),
            'target' => CreepTargetResource::make($this->whenLoaded('target')),
        ];
    }
}
