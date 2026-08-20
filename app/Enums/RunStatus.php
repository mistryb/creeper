<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}
