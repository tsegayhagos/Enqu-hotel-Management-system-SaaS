<?php

namespace App\Enums;

enum LaundryPaymentMethod: string
{
    case ChargeToRoom = 'charge_to_room';
    case Cash = 'cash';
    case Card = 'card';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::ChargeToRoom => 'Charge to Room Folio',
            self::Cash => 'Cash Payment',
            self::Card => 'Credit/Debit Card',
            self::Unpaid => 'Pending Payment',
        };
    }
}
