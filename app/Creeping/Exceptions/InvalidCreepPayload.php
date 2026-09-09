<?php

namespace App\Creeping\Exceptions;

use RuntimeException;

/**
 * Thrown when a driver or callback hands back data we cannot file as a reading.
 */
class InvalidCreepPayload extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function because(array $errors): self
    {
        $messages = collect($errors)->flatten()->implode(' ');

        return new self("The creep payload was not valid: {$messages}");
    }
}
