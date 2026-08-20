<?php

namespace App\Models;

use App\Enums\Availability;
use Database\Factories\ProductSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * The structured product data a successful run produced.
 *
 * @property int $id
 * @property int $creep_run_id
 * @property int $creep_target_id
 * @property string|null $title
 * @property string|null $brand
 * @property string|null $sku
 * @property int|null $price_amount Minor units, e.g. pence
 * @property string|null $currency
 * @property Availability $availability
 * @property string|null $rating
 * @property int|null $review_count
 * @property string|null $image_url
 * @property array<string, mixed>|null $extra
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CreepTarget $target
 * @property-read CreepRun $run
 */
#[Fillable([
    'creep_run_id', 'creep_target_id', 'title', 'brand', 'sku', 'price_amount',
    'currency', 'availability', 'rating', 'review_count', 'image_url', 'extra', 'captured_at',
])]
class ProductSnapshot extends Model
{
    /** @use HasFactory<ProductSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => Availability::class,
            'price_amount' => 'integer',
            'review_count' => 'integer',
            'extra' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CreepTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CreepTarget::class, 'creep_target_id');
    }

    /** @return BelongsTo<CreepRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CreepRun::class, 'creep_run_id');
    }

    /**
     * The price as a human-readable string, e.g. "£24.99".
     */
    public function formattedPrice(): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }

        $currency = $this->currency ?? 'USD';
        $formatter = new NumberFormatter(config('app.locale', 'en'), NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->price_amount / 100, $currency) ?: null;
    }
}
