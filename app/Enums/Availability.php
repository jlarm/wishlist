<?php

namespace App\Enums;

enum Availability: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Unavailable = 'unavailable';

    /**
     * Human readable label for the availability.
     */
    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::OutOfStock => 'Out of stock',
            self::Unavailable => 'Link looks broken',
        };
    }
}
