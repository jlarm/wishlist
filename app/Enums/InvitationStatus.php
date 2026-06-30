<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
