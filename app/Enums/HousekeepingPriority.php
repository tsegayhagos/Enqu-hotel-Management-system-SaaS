<?php

namespace App\Enums;

enum HousekeepingPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::Urgent => 'Urgent',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Normal => 'info',
            self::Urgent => 'danger',
        };
    }
}
