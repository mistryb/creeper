<?php

namespace App\Enums;

/**
 * What happened to one fact between two readings of a page.
 */
enum ChangeKind: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Changed = 'changed';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'New',
            self::Removed => 'Gone',
            self::Changed => 'Changed',
        };
    }
}
