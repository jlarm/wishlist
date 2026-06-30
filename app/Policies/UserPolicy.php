<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users (admin area).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && ! $user->isDisabled();
    }

    /**
     * Determine whether the user can update (disable / change role of) another user.
     *
     * Admins may manage other users but never themselves, preventing an admin
     * from locking themselves out or removing their own admin access.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->isDisabled() && $user->id !== $model->id;
    }
}
