<?php

namespace App\Enums;

enum LaundryCategory: string
{
    case Apparel = 'apparel';
    case Delicate = 'delicate';
    case Linen = 'linen';
    case Uniform = 'uniform';

    public function label(): string
    {
        return match ($this) {
            self::Apparel => 'Apparel & Clothing',
            self::Delicate => 'Delicates & Silks',
            self::Linen => 'Hotel Linen & Bedding',
            self::Uniform => 'Staff Uniforms',
        };
    }
}
