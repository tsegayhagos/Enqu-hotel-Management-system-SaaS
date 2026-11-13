<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Complimentary = 'complimentary';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::CreditCard => 'Credit Card',
            self::DebitCard => 'Debit Card',
            self::MobileMoney => 'Mobile Money',
            self::BankTransfer => 'Bank Transfer',
            self::Complimentary => 'Complimentary',
            self::None => 'None',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::CreditCard, self::DebitCard => 'primary',
            self::MobileMoney => 'accent',
            self::BankTransfer => 'neutral',
            self::Complimentary => 'warning',
            self::None => 'neutral',
        };
    }
}
