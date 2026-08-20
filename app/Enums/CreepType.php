<?php

namespace App\Enums;

/**
 * The kind of creeping a target is subject to.
 *
 * Only product creeping exists today. New types get their own snapshot
 * table and payload object; everything else in the pipeline is shared.
 */
enum CreepType: string
{
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
        };
    }
}
