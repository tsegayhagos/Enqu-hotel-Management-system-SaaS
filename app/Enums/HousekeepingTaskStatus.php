<?php

namespace App\Enums;

enum HousekeepingTaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Inspected = 'inspected';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Inspected => 'Needs Inspection',
            self::Completed => 'Completed & Clean',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::InProgress => 'info',
            self::Inspected => 'warning',
            self::Completed => 'success',
        };
    }

    public function nextStatus(): ?self
    {
        return match ($this) {
            self::Pending => self::InProgress,
            self::InProgress => self::Inspected,
            self::Inspected => self::Completed,
            self::Completed => null,
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress],
            self::InProgress => [self::Inspected, self::Completed],
            self::Inspected => [self::Completed, self::Pending],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
