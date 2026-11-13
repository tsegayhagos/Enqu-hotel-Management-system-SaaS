<?php

namespace App\Enums;

enum MaintenancePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Emergency => 'Emergency',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Medium => 'info',
            self::High => 'warning',
            self::Emergency => 'danger',
        };
    }
}
