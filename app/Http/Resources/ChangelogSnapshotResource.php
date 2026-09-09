<?php

namespace App\Http\Resources;

use App\Models\ChangelogSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangelogSnapshot
 */
class ChangelogSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product,
            'latest_version' => $this->latest_version,
            'latest_released_on' => $this->latest_released_on?->toDateString(),
            'release_count' => $this->release_count,
            'feature_count' => $this->feature_count,
            // Already normalised and length-capped by ChangelogPayload, so it
            // goes to the browser as it is stored.
            'releases' => $this->releases,
            'captured_at' => $this->captured_at->toIso8601String(),
        ];
    }
}
