<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RoomType> */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Room';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'base_price' => '100.00',
            'extra_person_fee' => '15.00',
            'amenities' => ['Wi-Fi', 'TV'],
        ];
    }
}
