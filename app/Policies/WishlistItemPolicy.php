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

        // Surprises are for admins only, and never their owner.
        if ($wishlistItem->isSurprise()) {
            return $this->managesSurprise($user, $wishlistItem);
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
     * Determine whether the user can put a surprise item on the recipient's
     * list. Only admins may, and never on their own list.
     */
    public function createFor(User $user, User $recipient): bool
    {
        return $user->isAdmin()
            && ! $user->isDisabled()
            && ! $recipient->isDisabled()
            && $user->id !== $recipient->id;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Surprises are looked after by the admins, never by their owner.
     */
    public function update(User $user, WishlistItem $wishlistItem): bool
    {
        if ($wishlistItem->isSurprise()) {
            return $this->managesSurprise($user, $wishlistItem);
        }

        return $user->id === $wishlistItem->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WishlistItem $wishlistItem): bool
    {
        return $this->update($user, $wishlistItem);
    }

    /**
     * Determine whether the user can archive the item as received or tick it
     * off their thank-you list. Only the owner can, and only for their own
     * wishes, since they never know about surprises.
     */
    public function receive(User $user, WishlistItem $wishlistItem): bool
    {
        return ! $wishlistItem->isSurprise() && $user->id === $wishlistItem->user_id;
    }

    /**
     * Determine whether the user can mark the item as purchased.
     *
     * Only admins (the gift-buying parents) may claim items, and never their
     * own. Everyone else just keeps a list.
     */
    public function purchase(User $user, WishlistItem $wishlistItem): bool
    {
        if ($user->isDisabled() || ! $user->isAdmin()) {
            return false;
        }

        return $user->id !== $wishlistItem->user_id;
    }

    /**
     * Whether the user is an active admin other than the surprise's owner.
     */
    private function managesSurprise(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->isAdmin()
            && ! $user->isDisabled()
            && $user->id !== $wishlistItem->user_id;
    }
}
