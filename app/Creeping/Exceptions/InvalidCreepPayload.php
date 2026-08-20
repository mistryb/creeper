<?php

namespace App\Creeping\Exceptions;

use RuntimeException;

/**
 * Thrown when a driver or callback hands back data that isn't a usable product.
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
