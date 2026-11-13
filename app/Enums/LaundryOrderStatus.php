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

    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Received => self::InWash,
            self::InWash => self::Ironing,
            self::Ironing => self::Ready,
            self::Ready => self::Delivered,
            self::Delivered => null,
            self::Cancelled => null,
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::InWash, self::Cancelled],
            self::InWash => [self::Ironing, self::Cancelled],
            self::Ironing => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered, self::Cancelled],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
