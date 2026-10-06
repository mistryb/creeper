<?php

namespace App\Policies;

use App\Models\WatchedPage;
use App\Models\User;

/**
 * A watched page belongs to whoever owns the business its competitor is a
 * competitor of, and nobody else may see or change it.
 */
class WatchedPagePolicy
{
    public function view(User $user, WatchedPage $watchedPage): bool
    {
        return $this->owns($user, $watchedPage);
    }

    public function update(User $user, WatchedPage $watchedPage): bool
    {
        return $this->owns($user, $watchedPage);
    }

    public function delete(User $user, WatchedPage $watchedPage): bool
    {
        return $this->owns($user, $watchedPage);
    }

    private function owns(User $user, WatchedPage $watchedPage): bool
    {
        return $user->id === $watchedPage->competitor->business->user_id;
    }
}
