<?php

namespace App\Services;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Batch room generator, e.g. floor 1, rooms 101-120. All-or-nothing. */
class BulkRoomCreator
{
    public const MAX_ROOMS = 500;

    /**
     * @return Collection<int, Room>
     *
     * @throws ValidationException when any generated number already exists in the hotel
     */
    public function create(RoomType $type, int $floor, int $start, int $end, string $prefix = ''): Collection
    {
        $numbers = [];

        for ($number = $start; $number <= $end; $number++) {
            $numbers[] = $prefix.$number;
        }

        $existing = Room::query()->whereIn('room_number', $numbers)->pluck('room_number')->all();

        if ($existing !== []) {
            throw $this->duplicates($existing);
        }

        try {
            return DB::transaction(fn (): Collection => collect($numbers)->map(
                fn (string $roomNumber): Room => Room::create([
                    'room_type_id' => $type->id,
                    'room_number' => $roomNumber,
                    'floor_number' => $floor,
                    'status' => RoomStatus::VacantClean,
                ])
            ));
        } catch (UniqueConstraintViolationException) {
            // Another request created one of the numbers between our check and the insert.
            throw $this->duplicates($numbers);
        }
    }

    /** @param  list<string>  $numbers */
    private function duplicates(array $numbers): ValidationException
    {
        $shown = implode(', ', array_slice($numbers, 0, 10));
        $more = count($numbers) > 10 ? ' and '.(count($numbers) - 10).' more' : '';

        return ValidationException::withMessages([
            'start_number' => "These room numbers already exist in this hotel: {$shown}{$more}. Nothing was created.",
        ]);
    }
}
