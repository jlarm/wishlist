<?php

namespace App\Enums;

/**
 * Why a claimed item disappeared from the claimer's view.
 */
enum ClaimRemovalReason: string
{
    case Deleted = 'deleted';
    case Hidden = 'hidden';
    case Received = 'received';
}
