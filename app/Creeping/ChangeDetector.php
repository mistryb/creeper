<?php

namespace App\Creeping;

use App\Enums\ChangeDirection;
use App\Models\ProductSnapshot;
use Illuminate\Support\Carbon;

/**
 * Works out what moved between two consecutive snapshots.
 *
 * The snapshots keep the machine-readable truth for the price chart; the
 * changes this produces are the human-readable log, so values are stored
 * already formatted.
 */
class ChangeDetector
{
    /**
     * Fields worth telling someone about. Rating and review counts drift
     * constantly and would drown the signal, so they're left out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compare(ProductSnapshot $from, ProductSnapshot $to): array
    {
        $detectedAt = $to->captured_at ?? Carbon::now();

        $changes = [];

        if ($from->price_amount !== $to->price_amount) {
            $changes[] = [
                'field' => 'price',
                'old_value' => $from->formattedPrice(),
                'new_value' => $to->formattedPrice(),
                'direction' => $this->priceDirection($from, $to),
            ];
        }

        if ($from->availability !== $to->availability) {
            $changes[] = [
                'field' => 'availability',
                'old_value' => $from->availability->label(),
                'new_value' => $to->availability->label(),
                'direction' => ChangeDirection::Changed,
            ];
        }

        if ($from->title !== $to->title) {
            $changes[] = [
                'field' => 'title',
                'old_value' => $from->title,
                'new_value' => $to->title,
                'direction' => ChangeDirection::Changed,
            ];
        }

        return array_map(fn (array $change): array => [
            ...$change,
            'creep_target_id' => $to->creep_target_id,
            'from_snapshot_id' => $from->id,
            'to_snapshot_id' => $to->id,
            'detected_at' => $detectedAt,
        ], $changes);
    }

    /**
     * A price appearing or disappearing isn't a rise or a fall.
     */
    protected function priceDirection(ProductSnapshot $from, ProductSnapshot $to): ChangeDirection
    {
        if ($from->price_amount === null || $to->price_amount === null) {
            return ChangeDirection::Changed;
        }

        return $to->price_amount > $from->price_amount
            ? ChangeDirection::Up
            : ChangeDirection::Down;
    }
}
