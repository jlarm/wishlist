<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Reserved = 'reserved';
    case Purchased = 'purchased';
    case Delivered = 'delivered';

    /**
     * Human readable label for the claim status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Purchased => 'Bought',
            self::Delivered => 'Delivered',
        };
    }
}
