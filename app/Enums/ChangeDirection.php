<?php

namespace App\Enums;

enum ChangeDirection: string
{
    case Up = 'up';
    case Down = 'down';
    case Changed = 'changed';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Increased',
            self::Down => 'Decreased',
            self::Changed => 'Changed',
        };
    }
}
