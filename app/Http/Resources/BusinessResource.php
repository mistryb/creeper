<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'description' => $this->description,
            'digest_frequency' => $this->digest_frequency->value,
            'digest_frequency_label' => $this->digest_frequency->label(),
            'api_key_id' => $this->api_key_id === null ? null : (string) $this->api_key_id,
            // Only sent where the key is loaded.
            'api_key_label' => $this->whenLoaded('apiKey', fn (): ?string => $this->apiKey?->label()),
            'last_digest_at' => $this->last_digest_at?->toIso8601String(),
            'next_digest_at' => $this->next_digest_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
