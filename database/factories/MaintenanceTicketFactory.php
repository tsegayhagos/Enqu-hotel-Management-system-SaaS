<?php

namespace Database\Factories;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Models\Hotel;
use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTicket>
 */
class MaintenanceTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_id' => null,
            'location_label' => 'Main Corridor',
            'reported_by_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(MaintenanceCategory::cases()),
            'priority' => MaintenancePriority::Medium,
            'status' => MaintenanceTicketStatus::Open,
            'blocks_room' => false,
            'estimated_cost' => fake()->randomFloat(2, 20, 450),
        ];
    }
}
