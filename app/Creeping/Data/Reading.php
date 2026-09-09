<?php

namespace App\Creeping\Data;

use App\Models\CreepChange;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored reading, and the news in it.
 *
 * Snapshots are typed per creep type — a product snapshot and a changelog
 * snapshot have almost nothing in common — so the shared pipeline holds them
 * as models and only ever hands them back to the type that made them.
 */
final readonly class Reading
{
    /**
     * @param  Model  $snapshot  The row this creep produced.
     * @param  array<int, array<string, mixed>>  $changes  Attributes for the {@see CreepChange} rows it revealed.
     */
    public function __construct(
        public Model $snapshot,
        public array $changes = [],
    ) {}
}
