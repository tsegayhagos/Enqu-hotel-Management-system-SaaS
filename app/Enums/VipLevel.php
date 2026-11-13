<?php

namespace App\Enums;

enum VipLevel: string
{
    case Standard = 'standard';
    case Silver = 'silver';
    case Gold = 'gold';
    case Vip = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Vip => 'VIP',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Standard => 'neutral',
            self::Silver => 'info',
            self::Gold => 'warning',
            self::Vip => 'danger',
        };
    }
}
