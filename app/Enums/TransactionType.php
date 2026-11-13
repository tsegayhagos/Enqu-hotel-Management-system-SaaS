<?php

namespace App\Enums;

enum TransactionType: string
{
    case RoomCharge = 'room_charge';
    case Tax = 'tax';
    case Laundry = 'laundry';
    case RestaurantPos = 'restaurant_pos';
    case Minibar = 'minibar';
    case Fee = 'fee';
    case Payment = 'payment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::RoomCharge => 'Room Charge',
            self::Tax => 'Tax',
            self::Laundry => 'Laundry',
            self::RestaurantPos => 'Restaurant / POS',
            self::Minibar => 'Minibar',
            self::Fee => 'Extra Fee',
            self::Payment => 'Payment',
            self::Refund => 'Refund',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::RoomCharge => 'primary',
            self::Tax => 'neutral',
            self::Laundry => 'accent',
            self::RestaurantPos => 'warning',
            self::Minibar => 'warning',
            self::Fee => 'neutral',
            self::Payment => 'success',
            self::Refund => 'danger',
        };
    }

    /**
     * Determines whether this transaction type increases the folio's charges.
     */
    public function isCharge(): bool
    {
        return match ($this) {
            self::RoomCharge, self::Tax, self::Laundry, self::RestaurantPos, self::Minibar, self::Fee => true,
            self::Payment, self::Refund => false,
        };
    }

    /**
     * Determines whether this transaction type is a payment/credit settlement.
     */
    public function isPaymentOrCredit(): bool
    {
        return match ($this) {
            self::Payment, self::Refund => true,
            default => false,
        };
    }
}
