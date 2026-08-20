<?php

namespace App\Enums;

enum TargetStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Failed => 'Failed',
        };
    }
}
