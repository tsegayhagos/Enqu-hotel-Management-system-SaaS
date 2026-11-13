<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TransactionType;
use App\Models\Folio;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FolioTransaction>
 */
class FolioTransactionFactory extends Factory
{
    protected $model = FolioTransaction::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'folio_id' => Folio::factory(),
            'user_id' => null,
            'transaction_type' => TransactionType::RoomCharge,
            'description' => 'Daily Room Charge',
            'amount' => '100.00',
            'reference_id' => null,
            'payment_method' => PaymentMethod::None,
            'is_night_audit_post' => false,
            'is_immutable' => false,
            'posted_at' => now(),
        ];
    }
}
