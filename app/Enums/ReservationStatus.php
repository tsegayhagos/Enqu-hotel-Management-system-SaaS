<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Confirmed => 'info',
            self::CheckedIn => 'success',
            self::CheckedOut => 'neutral',
            self::Cancelled => 'danger',
            self::NoShow => 'warning',
        };
    }
}
