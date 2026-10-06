<?php

namespace App\Creeping;

use App\Enums\ChangeKind;
use App\Models\PageSnapshot;

/**
 * Works out what moved between two readings of a page.
 *
 * Facts are lined up by label: one only in the new reading was added, one
 * only in the old was removed, and one in both with a different value
 * changed. Values are compared ignoring case and spacing, so a reader
 * reformatting "$20 / month" as "$20/month" is not news.
 */
class FactDetector
{
    /**
     * @return list<array<string, mixed>> Attributes for the CreepChange rows.
     */
    public function compare(PageSnapshot $previous, PageSnapshot $current): array
    {
        $before = $previous->factsByKey();
        $after = $current->factsByKey();

        $changes = [];

        foreach ($after as $key => $fact) {
            if (! isset($before[$key])) {
                $changes[] = $this->change($previous, $current, ChangeKind::Added, $fact['label'], null, $fact['value']);
            } elseif ($this->normalise($before[$key]['value']) !== $this->normalise($fact['value'])) {
                $changes[] = $this->change($previous, $current, ChangeKind::Changed, $fact['label'], $before[$key]['value'], $fact['value']);
            }
        }

        foreach ($before as $key => $fact) {
            if (! isset($after[$key])) {
                $changes[] = $this->change($previous, $current, ChangeKind::Removed, $fact['label'], $fact['value'], null);
            }
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    private function change(PageSnapshot $previous, PageSnapshot $current, ChangeKind $kind, string $label, ?string $old, ?string $new): array
    {
        return [
            'watched_page_id' => $current->watched_page_id,
            'from_snapshot_id' => $previous->id,
            'to_snapshot_id' => $current->id,
            'label' => $label,
            'old_value' => $old,
            'new_value' => $new,
            'kind' => $kind,
            'detected_at' => $current->captured_at,
        ];
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $value) ?? '');
    }
}
