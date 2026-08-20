<?php

namespace App\Policies;

use App\Models\CreepTarget;
use App\Models\User;

/**
 * Creeper has single-user accounts: you can touch your own targets, nobody else's.
 */
class CreepTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreepTarget $target): bool
    {
        return $this->owns($user, $target);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreepTarget $target): bool
    {
        return $this->owns($user, $target);
    }

    public function delete(User $user, CreepTarget $target): bool
    {
        return $this->owns($user, $target);
    }

    private function owns(User $user, CreepTarget $target): bool
    {
        return $user->id === $target->user_id;
    }
}
