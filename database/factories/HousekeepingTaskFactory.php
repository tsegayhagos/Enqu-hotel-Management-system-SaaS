<?php

namespace Database\Factories;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HousekeepingTask>
 */
class HousekeepingTaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_id' => Room::factory(),
            'task_type' => fake()->randomElement(HousekeepingTaskType::cases()),
            'priority' => HousekeepingPriority::Normal,
            'status' => HousekeepingTaskStatus::Pending,
            'due_date' => today()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
