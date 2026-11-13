<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('now', '+7 days');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(1, 5).' days');
        $nights = max(1, (int) $checkIn->diff($checkOut)->format('%a'));
        $dailyRate = fake()->randomElement([100.00, 150.00, 250.00]);

        return [
            'hotel_id' => Hotel::factory(),
            'reservation_code' => 'ENK-'.date('Y').'-'.fake()->unique()->numerify('#####'),
            'guest_id' => Guest::factory(),
            'room_id' => null,
            'room_type_id' => RoomType::factory(),
            'check_in_date' => $checkIn->format('Y-m-d'),
            'check_out_date' => $checkOut->format('Y-m-d'),
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
            'adults_count' => fake()->numberBetween(1, 2),
            'children_count' => 0,
            'daily_rate' => number_format($dailyRate, 2, '.', ''),
            'total_amount' => number_format($dailyRate * $nights, 2, '.', ''),
            'advance_deposit_paid' => '0.00',
            'keycard_number' => null,
            'status' => ReservationStatus::Confirmed,
            'source' => fake()->randomElement(ReservationSource::cases()),
            'special_requests' => fake()->optional()->sentence(),
            'cancellation_reason' => null,
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::CheckedIn,
            'actual_check_in_at' => now(),
            'keycard_number' => 'KEY-'.fake()->numerify('####'),
        ]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::CheckedOut,
            'actual_check_in_at' => now()->subDays(2),
            'actual_check_out_at' => now(),
            'keycard_number' => 'KEY-'.fake()->numerify('####'),
        ]);
    }
}
