<?php

namespace App\Enums;

enum LaundryServiceType: string
{
    case WashAndIron = 'wash_and_iron';
    case IronOnly = 'iron_only';
    case DryClean = 'dry_clean';

    public function label(): string
    {
        return match ($this) {
            self::WashAndIron => 'Wash & Iron',
            self::IronOnly => 'Iron Only (Pressing)',
            self::DryClean => 'Dry Cleaning',
        };
    }
}
