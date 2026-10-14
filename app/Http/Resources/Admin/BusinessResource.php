<?php

namespace App\Http\Resources\Admin;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company as the admin panel lists it, across every account. Expects
 * `user` loaded and `competitors_count` counted.
 *
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
            'owner_email' => $this->user->email,
            'digest_frequency_label' => $this->digest_frequency->label(),
            'competitors_count' => $this->competitors_count,
            'last_digest_at' => $this->last_digest_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
