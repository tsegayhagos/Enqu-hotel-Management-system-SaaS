<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'room_number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor_number' => 1,
            'status' => RoomStatus::VacantClean,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function status(RoomStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
