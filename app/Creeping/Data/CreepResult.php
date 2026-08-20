<?php

namespace App\Creeping\Data;

use App\Enums\CreepOutcome;

/**
 * What a driver hands back from one attempt at a target.
 */
final readonly class CreepResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public CreepOutcome $outcome,
        public array $payload = [],
        public ?string $error = null,
    ) {}

    /**
     * The driver came back with product data.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function succeeded(array $payload): self
    {
        return new self(CreepOutcome::Succeeded, $payload);
    }

    /**
     * The agent took the job and will post results to the callback URL later.
     */
    public static function pending(): self
    {
        return new self(CreepOutcome::Pending);
    }

    /**
     * The creep could not be completed.
     */
    public static function failed(string $error): self
    {
        return new self(CreepOutcome::Failed, error: $error);
    }
}
