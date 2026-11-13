<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Hotel> */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Hotel';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'check_in_time' => '14:00:00',
            'check_out_time' => '11:00:00',
            'tax_rate' => 15.00,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
