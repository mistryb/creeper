<?php

namespace App\Http\Resources;

use App\Models\ProductSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductSnapshot
 */
class ProductSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'brand' => $this->brand,
            'sku' => $this->sku,
            // Minor units. The browser formats it with Intl.NumberFormat, so
            // the currency renders in the reader's locale, not the server's.
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'availability' => $this->availability->value,
            'availability_label' => $this->availability->label(),
            'rating' => $this->rating === null ? null : (float) $this->rating,
            'review_count' => $this->review_count,
            'image_url' => $this->image_url,
            'captured_at' => $this->captured_at->toIso8601String(),
        ];
    }
}
