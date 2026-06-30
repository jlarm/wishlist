<?php

namespace App\Policies;

use App\Enums\VisibilityStatus;
use App\Models\User;
use App\Models\WishlistItem;

class WishlistItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ! $user->isDisabled();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WishlistItem $wishlistItem): bool
    {
        if ($user->isDisabled()) {
            return false;
        }

        if ($user->id === $wishlistItem->user_id) {
            return true;
        }

        return $wishlistItem->visibility_status === VisibilityStatus::Visible;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! $user->isDisabled();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->id === $wishlistItem->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->id === $wishlistItem->user_id;
    }

    /**
     * Determine whether the user can mark the item as purchased.
     *
     * Only non-owners may purchase, and never their own item.
     */
    public function purchase(User $user, WishlistItem $wishlistItem): bool
    {
        if ($user->isDisabled()) {
            return false;
        }

        return $user->id !== $wishlistItem->user_id;
    }
}
