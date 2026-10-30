<?php

namespace App\Enums;

enum RoomStatus: string
{
    case VacantClean = 'vacant_clean';
    case VacantDirty = 'vacant_dirty';
    case Occupied = 'occupied';
    case Cleaning = 'cleaning';
    case OutOfOrder = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::VacantClean => 'Vacant Clean',
            self::VacantDirty => 'Vacant Dirty',
            self::Occupied => 'Occupied',
            self::Cleaning => 'Cleaning In Progress',
            self::OutOfOrder => 'Out of Order',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::VacantClean => 'success',
            self::VacantDirty => 'warning',
            self::Occupied => 'primary',
            self::Cleaning => 'info',
            self::OutOfOrder => 'danger',
        };
    }
}
