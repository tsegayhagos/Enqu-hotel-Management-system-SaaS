<?php

namespace App\Enums;

enum HousekeepingTaskType: string
{
    case CheckoutClean = 'checkout_clean';
    case StayoverClean = 'stayover_clean';
    case DeepClean = 'deep_clean';
    case Turndown = 'turndown';

    public function label(): string
    {
        return match ($this) {
            self::CheckoutClean => 'Checkout Clean',
            self::StayoverClean => 'Stayover Clean',
            self::DeepClean => 'Deep Clean',
            self::Turndown => 'Turndown Service',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::CheckoutClean => 'warning',
            self::StayoverClean => 'info',
            self::DeepClean => 'accent',
            self::Turndown => 'neutral',
        };
    }
}
