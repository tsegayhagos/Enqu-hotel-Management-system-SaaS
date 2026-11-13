<?php

namespace App\Enums;

enum FolioStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case Closed = 'closed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Settled => 'Settled',
            self::Closed => 'Closed',
            self::Void => 'Void',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Open => 'primary',
            self::Settled => 'success',
            self::Closed => 'neutral',
            self::Void => 'danger',
        };
    }

    public function canAcceptCharges(): bool
    {
        return $this === self::Open;
    }

    public function canAcceptPayments(): bool
    {
        return in_array($this, [self::Open, self::Settled], true);
    }
}
