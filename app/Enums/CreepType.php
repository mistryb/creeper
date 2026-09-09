<?php

namespace App\Enums;

use App\Creeping\Contracts\CreepInstructions;
use App\Creeping\Instructions\ChangelogInstructions;
use App\Creeping\Instructions\ProductInstructions;

/**
 * The kind of creeping a target is subject to — the instructions the user
 * hands their creeper.
 *
 * Each type is a complete set of instructions: the agent that reads the page,
 * the digest that is put in front of it, the table its readings are stored in
 * and what counts as a change. Everything else in the pipeline — targets,
 * scheduling, runs, retries, notifications, the UI — is shared.
 */
enum CreepType: string
{
    /** Watch one product's price, stock and rating. */
    case Product = 'product';

    /** Watch a product's changelog for the features it ships. */
    case Changelog = 'changelog';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Changelog => 'Changelog',
        };
    }

    /**
     * The instructions this type creeps by.
     */
    public function instructions(): CreepInstructions
    {
        return match ($this) {
            self::Product => new ProductInstructions,
            self::Changelog => new ChangelogInstructions,
        };
    }
}
