<?php

namespace Database\Factories;

use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoomRate> */
class RoomRateFactory extends Factory
{
    protected $model = RoomRate::class;

    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'rate_name' => 'Seasonal Rate',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'price_per_night' => '150.00',
            'days_of_week' => null,
            'is_active' => true,
        ];
    }
}
