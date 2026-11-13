<?php

namespace App\Exceptions;

use App\Enums\RoomStatus;
use App\Models\Room;
use DomainException;

class InvalidRoomTransition extends DomainException
{
    public static function notAllowed(Room $room, RoomStatus $from, RoomStatus $to): self
    {
        return new self(sprintf(
            'Room %s cannot change from "%s" to "%s".',
            $room->room_number,
            $from->label(),
            $to->label(),
        ));
    }

    public static function manualOccupancyChange(Room $room): self
    {
        return new self(sprintf(
            'Room %s: occupancy changes only through check-in / check-out.',
            $room->room_number,
        ));
    }
}
