<?php

namespace App\Enums;

enum IdType: string
{
    case Passport = 'passport';
    case NationalId = 'national_id';
    case DriversLicense = 'drivers_license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Passport => 'Passport',
            self::NationalId => 'National ID',
            self::DriversLicense => "Driver's License",
            self::Other => 'Other',
        };
    }
}
