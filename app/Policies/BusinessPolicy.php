<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * A business belongs to the account that set it up, and nobody else may see
 * or change it.
 */
class BusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    public function delete(User $user, Business $business): bool
    {
        return $this->owns($user, $business);
    }

    private function owns(User $user, Business $business): bool
    {
        return $user->id === $business->user_id;
    }
}
