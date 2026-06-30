<?php

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Only administrators may manage invitations.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isAdmin() || $user->isDisabled()) {
            return false;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can resend the invitation.
     */
    public function resend(User $user, Invitation $invitation): bool
    {
        return $invitation->status !== InvitationStatus::Accepted;
    }

    /**
     * Determine whether the user can revoke (delete) the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        return $invitation->status === InvitationStatus::Pending;
    }
}
