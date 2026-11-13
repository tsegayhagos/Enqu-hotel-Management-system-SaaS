<?php

namespace App\Enums;

enum MaintenanceCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case AcHvac = 'ac_hvac';
    case Furniture = 'furniture';
    case Appliance = 'appliance';
    case Structural = 'structural';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plumbing => 'Plumbing',
            self::Electrical => 'Electrical',
            self::AcHvac => 'AC / HVAC',
            self::Furniture => 'Furniture & Fixtures',
            self::Appliance => 'TV & Appliances',
            self::Structural => 'Structural / Doors / Windows',
            self::Other => 'Other',
        };
    }
}
