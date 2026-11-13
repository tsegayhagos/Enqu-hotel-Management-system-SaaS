<?php

namespace Database\Factories;

use App\Enums\FolioStatus;
use App\Models\Folio;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folio>
 */
class FolioFactory extends Factory
{
    protected $model = Folio::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'reservation_id' => null,
            'guest_id' => null,
            'room_id' => null,
            'folio_number' => 'FOL-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'guest_name' => fake()->name(),
            'status' => FolioStatus::Open,
            'total_charges' => '0.00',
            'total_payments' => '0.00',
            'balance' => '0.00',
            'currency' => 'USD',
            'notes' => null,
            'closed_at' => null,
        ];
    }
}
