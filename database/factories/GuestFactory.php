<?php

namespace Database\Factories;

use App\Enums\IdType;
use App\Enums\VipLevel;
use App\Models\Guest;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'id_type' => fake()->randomElement(IdType::cases()),
            'id_number' => 'ID-'.fake()->numerify('######'),
            'nationality' => fake()->country(),
            'car_plate_number' => 'ET '.fake()->bothify('?#-#####'),
            'vip_level' => fake()->randomElement(VipLevel::cases()),
            'is_blacklisted' => false,
            'blacklist_reason' => null,
            'total_stays_count' => fake()->numberBetween(0, 10),
            'special_preferences' => fake()->optional()->sentence(),
        ];
    }

    public function blacklisted(string $reason = 'Property damage'): static
    {
        return $this->state(fn () => [
            'is_blacklisted' => true,
            'blacklist_reason' => $reason,
        ]);
    }

    public function vip(): static
    {
        return $this->state(fn () => [
            'vip_level' => VipLevel::Vip,
        ]);
    }
}
