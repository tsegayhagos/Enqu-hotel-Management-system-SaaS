<?php

namespace App\Enums;

enum ReservationSource: string
{
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Website = 'website';
    case OtaBooking = 'ota_booking';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-In',
            self::Phone => 'Phone',
            self::Website => 'Website',
            self::OtaBooking => 'OTA Booking',
        };
    }
}
