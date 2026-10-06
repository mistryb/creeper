<?php

namespace App\Http\Resources;

use App\Models\PageSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PageSnapshot
 */
class PageSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'summary' => $this->summary,
            'facts' => $this->facts,
            'captured_at' => $this->captured_at->toIso8601String(),
        ];
    }
}
