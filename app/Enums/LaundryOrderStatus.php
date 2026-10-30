<?php

namespace App\Enums;

enum LaundryOrderStatus: string
{
    case Received = 'received';
    case InWash = 'in_wash';
    case Ironing = 'ironing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::InWash => 'In Washing',
            self::Ironing => 'Ironing / Pressing',
            self::Ready => 'Ready for Delivery',
            self::Delivered => 'Delivered to Guest',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Received => 'neutral',
            self::InWash => 'info',
            self::Ironing => 'warning',
            self::Ready => 'accent',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }
}
