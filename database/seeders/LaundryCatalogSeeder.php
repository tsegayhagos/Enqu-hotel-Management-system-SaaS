<?php

namespace Database\Seeders;

use App\Enums\LaundryCategory;
use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Enums\LaundryServiceType;
use App\Enums\ReservationStatus;
use App\Models\Hotel;
use App\Models\LaundryOrder;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\Reservation;
use App\Services\LaundryService;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

class LaundryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::where('slug', 'grand-enku')->first();
        if (! $hotel) {
            return;
        }

        Tenant::runAs($hotel->id, function () use ($hotel) {
            $catalogItems = [
                [
                    'item_name' => "Men's Shirt",
                    'category' => LaundryCategory::Apparel,
                    'service_type' => LaundryServiceType::WashAndIron,
                    'standard_price' => '8.00',
                    'express_price' => '12.00',
                ],
                [
                    'item_name' => 'Suit (2-Piece)',
                    'category' => LaundryCategory::Delicate,
                    'service_type' => LaundryServiceType::DryClean,
                    'standard_price' => '22.00',
                    'express_price' => '33.00',
                ],
                [
                    'item_name' => 'Evening Dress',
                    'category' => LaundryCategory::Delicate,
                    'service_type' => LaundryServiceType::DryClean,
                    'standard_price' => '20.00',
                    'express_price' => '30.00',
                ],
                [
                    'item_name' => 'Trousers / Slacks',
                    'category' => LaundryCategory::Apparel,
                    'service_type' => LaundryServiceType::IronOnly,
                    'standard_price' => '6.00',
                    'express_price' => '9.00',
                ],
                [
                    'item_name' => 'Bath Towel',
                    'category' => LaundryCategory::Linen,
                    'service_type' => LaundryServiceType::WashAndIron,
                    'standard_price' => '4.00',
                    'express_price' => '6.00',
                ],
                [
                    'item_name' => 'Bedsheet (King)',
                    'category' => LaundryCategory::Linen,
                    'service_type' => LaundryServiceType::WashAndIron,
                    'standard_price' => '10.00',
                    'express_price' => '15.00',
                ],
                [
                    'item_name' => 'Staff Chef Jacket',
                    'category' => LaundryCategory::Uniform,
                    'service_type' => LaundryServiceType::WashAndIron,
                    'standard_price' => '5.00',
                    'express_price' => '7.50',
                ],
            ];

            $createdServices = [];
            foreach ($catalogItems as $item) {
                $createdServices[] = LaundryServiceCatalog::updateOrCreate(
                    ['hotel_id' => $hotel->id, 'item_name' => $item['item_name']],
                    array_merge($item, ['is_active' => true])
                );
            }

            if (LaundryOrder::where('hotel_id', $hotel->id)->exists()) {
                return;
            }

            $laundryService = app(LaundryService::class);
            $activeStay = Reservation::where('hotel_id', $hotel->id)
                ->where('status', ReservationStatus::CheckedIn)
                ->whereNotNull('room_id')
                ->first();

            if ($activeStay && count($createdServices) >= 2) {
                $order1 = $laundryService->createOrder($hotel, [
                    'room_id' => $activeStay->room_id,
                    'order_type' => LaundryOrderType::GuestValet,
                    'is_express' => true,
                    'payment_method' => LaundryPaymentMethod::ChargeToRoom,
                    'special_instructions' => 'Light starch on shirt, hang suit.',
                    'items' => [
                        ['laundry_service_id' => $createdServices[0]->id, 'quantity' => 2],
                        ['laundry_service_id' => $createdServices[1]->id, 'quantity' => 1],
                    ],
                ]);
                $laundryService->updateStatus($order1, LaundryOrderStatus::InWash);
            }

            // Internal hotel linen wash order
            if (count($createdServices) >= 6) {
                $laundryService->createOrder($hotel, [
                    'order_type' => LaundryOrderType::InHouseLinen,
                    'is_express' => false,
                    'payment_method' => LaundryPaymentMethod::Unpaid,
                    'special_instructions' => 'Floor 2 housekeeping morning batch',
                    'items' => [
                        ['laundry_service_id' => $createdServices[4]->id, 'quantity' => 15],
                        ['laundry_service_id' => $createdServices[5]->id, 'quantity' => 8],
                    ],
                ]);
            }
        });
    }
}
