<?php

namespace App\Creeping\Data;

use App\Models\CreepChange;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored reading, and the news in it.
 *
 * The snapshot this creep produced, and the changes it revealed against the
 * one before it.
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
