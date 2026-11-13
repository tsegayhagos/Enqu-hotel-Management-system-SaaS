<?php

namespace App\Enums;

enum LaundryOrderType: string
{
    case GuestValet = 'guest_valet';
    case InHouseLinen = 'in_house_linen';
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::GuestValet => 'Guest Valet',
            self::InHouseLinen => 'In-House Hotel Linen',
            self::WalkIn => 'Walk-In Customer',
        };
    }
}
