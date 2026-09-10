<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

/**
 * A key belongs to one person, and only that person may spend or destroy it.
 */
class ApiKeyPolicy
{
    public function view(User $user, ApiKey $key): bool
    {
        return $user->id === $key->user_id;
    }

    public function delete(User $user, ApiKey $key): bool
    {
        return $user->id === $key->user_id;
    }
}
