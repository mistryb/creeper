<?php

namespace App\Policies;

use App\Models\Digest;
use App\Models\User;

/**
 * A digest belongs to whoever owns the company it is about.
 */
class DigestPolicy
{
    public function view(User $user, Digest $digest): bool
    {
        return $user->id === $digest->business->user_id;
    }
}
