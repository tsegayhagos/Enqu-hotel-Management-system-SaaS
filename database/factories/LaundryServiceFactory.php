<?php

namespace Database\Factories;

use App\Enums\LaundryCategory;
use App\Enums\LaundryServiceType;
use App\Models\Hotel;
use App\Models\LaundryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryService>
 */
class LaundryServiceFactory extends Factory
{
    protected $model = LaundryService::class;

    public function definition(): array
    {
        $standard = fake()->randomElement([5.00, 8.00, 12.00, 20.00]);

        return [
            'hotel_id' => Hotel::factory(),
            'item_name' => fake()->words(2, true),
            'category' => fake()->randomElement(LaundryCategory::cases()),
            'service_type' => fake()->randomElement(LaundryServiceType::cases()),
            'standard_price' => number_format($standard, 2, '.', ''),
            'express_price' => number_format($standard * 1.5, 2, '.', ''),
            'is_active' => true,
        ];
    }
}
