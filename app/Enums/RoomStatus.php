<?php

namespace App\Enums;

enum RoomStatus: string
{
    case VacantClean = 'vacant_clean';
    case VacantDirty = 'vacant_dirty';
    case Occupied = 'occupied';
    case Cleaning = 'cleaning';
    case OutOfOrder = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::VacantClean => 'Vacant Clean',
            self::VacantDirty => 'Vacant Dirty',
            self::Occupied => 'Occupied',
            self::Cleaning => 'Cleaning In Progress',
            self::OutOfOrder => 'Out of Order',
        };
    }

    /** Maps to <Badge variant="..."> */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::VacantClean => 'success',
            self::VacantDirty => 'accent',
            self::Occupied => 'primary',
            self::Cleaning => 'warning',
            self::OutOfOrder => 'danger',
        };
    }

    /**
     * Room lifecycle state machine (Module 02).
     *
     *   VacantClean --check-in--> Occupied --check-out--> VacantDirty --start--> Cleaning --done--> VacantClean
     *   Any non-occupied state can be blocked (OutOfOrder); repairs return to VacantDirty
     *   so housekeeping sanitises the room before the next guest.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::VacantClean => [self::Occupied, self::VacantDirty, self::OutOfOrder],
            self::VacantDirty => [self::Cleaning, self::VacantClean, self::OutOfOrder],
            self::Cleaning => [self::VacantClean, self::VacantDirty, self::OutOfOrder],
            self::Occupied => [self::VacantDirty],
            self::OutOfOrder => [self::VacantDirty],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Statuses a person may set by hand. Occupied is only set by check-in,
     * and a room leaves Occupied only through check-out.
     *
     * @return list<self>
     */
    public static function manuallySettable(): array
    {
        return [self::VacantClean, self::VacantDirty, self::Cleaning, self::OutOfOrder];
    }

    /** Counts toward "available rooms" in occupancy maths (out-of-order rooms do not). */
    public function isSellable(): bool
    {
        return $this !== self::OutOfOrder;
    }
}
