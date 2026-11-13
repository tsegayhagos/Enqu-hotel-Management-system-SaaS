<?php

namespace Database\Factories;

use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Models\Hotel;
use App\Models\LaundryOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryOrder>
 */
class LaundryOrderFactory extends Factory
{
    protected $model = LaundryOrder::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'order_number' => 'LND-'.date('Y').'-'.fake()->unique()->numerify('#####'),
            'reservation_id' => null,
            'guest_id' => null,
            'room_id' => null,
            'order_type' => LaundryOrderType::GuestValet,
            'is_express' => false,
            'status' => LaundryOrderStatus::Received,
            'payment_method' => LaundryPaymentMethod::Cash,
            'total_amount' => '15.00',
            'special_instructions' => null,
            'received_at' => now(),
            'delivered_at' => null,
            'processed_by_user_id' => User::factory(),
        ];
    }
}
