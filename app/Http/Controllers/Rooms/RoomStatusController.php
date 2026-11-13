<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\RoomStatus;
use App\Exceptions\InvalidRoomTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\UpdateRoomStatusRequest;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** PATCH /rooms/{room}/status - quick, validated status change. */
class RoomStatusController extends Controller
{
    public function update(UpdateRoomStatusRequest $request, Room $room): RedirectResponse
    {
        $target = RoomStatus::from($request->validated('status'));

        try {
            $room->transitionTo($target, manual: true);
        } catch (InvalidRoomTransition $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back()->with('success', "Room {$room->room_number} is now {$target->label()}.");
    }
}
