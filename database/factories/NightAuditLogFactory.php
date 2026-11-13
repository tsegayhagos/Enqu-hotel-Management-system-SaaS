<?php

namespace Database\Factories;

use App\Enums\NightAuditStatus;
use App\Models\Hotel;
use App\Models\NightAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NightAuditLog>
 */
class NightAuditLogFactory extends Factory
{
    protected $model = NightAuditLog::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'audit_date' => fake()->unique()->date(),
            'occupied_rooms_count' => 5,
            'total_room_revenue_posted' => '500.00',
            'total_tax_posted' => '75.00',
            'status' => NightAuditStatus::Completed,
            'executed_by_user_id' => null,
            'notes' => 'Night audit test log',
        ];
    }
}
