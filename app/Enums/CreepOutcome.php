<?php

namespace App\Enums;

/**
 * What a driver reported back from a single creep attempt.
 */
enum CreepOutcome: string
{
    /** The driver returned product data. */
    case Succeeded = 'succeeded';

    /** The agent accepted the work and will post results to the callback later. */
    case Pending = 'pending';

    /** The driver could not creep the target. */
    case Failed = 'failed';
}
