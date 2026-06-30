<?php

namespace App\Enums;

enum VisibilityStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    /**
     * Human readable label for the visibility status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
