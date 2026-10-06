<?php

namespace App\Policies;

use App\Models\Competitor;
use App\Models\User;

/**
 * A competitor belongs to whoever owns the business it competes with.
 */
class CompetitorPolicy
{
    public function view(User $user, Competitor $competitor): bool
    {
        return $this->owns($user, $competitor);
    }

    public function update(User $user, Competitor $competitor): bool
    {
        return $this->owns($user, $competitor);
    }

    public function delete(User $user, Competitor $competitor): bool
    {
        return $this->owns($user, $competitor);
    }

    private function owns(User $user, Competitor $competitor): bool
    {
        return $user->id === $competitor->business->user_id;
    }
}
