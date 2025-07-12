<?php

namespace App;

enum Role: string
{
    case PARENT = 'parent';
    case RELATIVE = 'relative';
    case KID = 'kid';

    public function label(): string
    {
        return match ($this) {
            self::PARENT => 'Parent',
            self::RELATIVE => 'Relative',
            self::KID => 'Kid',
        };
    }
}
