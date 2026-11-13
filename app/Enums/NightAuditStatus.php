<?php

namespace App\Enums;

enum NightAuditStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
