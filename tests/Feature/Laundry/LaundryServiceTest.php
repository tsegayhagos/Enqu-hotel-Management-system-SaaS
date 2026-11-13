<?php

namespace Tests\Feature\Laundry;

use App\Enums\LaundryCategory;
use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Enums\LaundryServiceType;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\FolioTransaction;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\LaundryService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class LaundryServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $staff;

    protected RoomType $roomType;

    protected Room $occupiedRoom;

    protected Room $vacantRoom;

    protected LaundryServiceCatalog $shirtService;

    protected LaundryServiceCatalog $suitService;

    protected LaundryService $laundryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->staff = $this->makeStaff($this->hotel, SystemRole::LaundryStaff);
        $this->laundryService = app(LaundryService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room',
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'base_price' => '150.00',
            ]);

            $this->occupiedRoom = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '101',
                'floor_number' => 1,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);

            $this->vacantRoom = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '102',
                'floor_number' => 1,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);

            $guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Kenenisa',
                'last_name' => 'Bekele',
                'phone' => '+251911888999',
                'id_type' => 'passport',
                'id_number' => 'EP888999',
            ]);

            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-50001',
                'guest_id' => $guest->id,
                'room_id' => $this->occupiedRoom->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'daily_rate' => '150.00',
                'total_amount' => '300.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            app(CheckInService::class)->checkIn($reservation, $this->occupiedRoom->id);

            $this->shirtService = LaundryServiceCatalog::create([
                'hotel_id' => $this->hotel->id,
                'item_name' => "Men's Shirt",
                'category' => LaundryCategory::Apparel,
                'service_type' => LaundryServiceType::WashAndIron,
                'standard_price' => '10.00',
                'express_price' => '15.00',
                'is_active' => true,
            ]);

            $this->suitService = LaundryServiceCatalog::create([
                'hotel_id' => $this->hotel->id,
                'item_name' => 'Suit 2-Piece',
                'category' => LaundryCategory::Delicate,
                'service_type' => LaundryServiceType::DryClean,
                'standard_price' => '20.00',
                'express_price' => '30.00',
                'is_active' => true,
            ]);
        });
    }

    public function test_express_pricing_surcharge_applied_across_items(): void
    {
        $order = $this->laundryService->createOrder($this->hotel, [
            'order_type' => LaundryOrderType::WalkIn,
            'is_express' => true,
            'payment_method' => LaundryPaymentMethod::Cash,
            'items' => [
                ['laundry_service_id' => $this->shirtService->id, 'quantity' => 2], // 2 * 15.00 = 30.00
                ['laundry_service_id' => $this->suitService->id, 'quantity' => 1],  // 1 * 30.00 = 30.00
            ],
        ], $this->staff);

        $this->assertTrue($order->is_express);
        $this->assertEquals('60.00', $order->total_amount);
        $this->assertCount(2, $order->items);
        $this->assertEquals('15.00', $order->items->firstWhere('laundry_service_id', $this->shirtService->id)->unit_price);
    }

    public function test_charge_to_room_rejects_room_without_checked_in_reservation(): void
    {
        $this->expectException(ValidationException::class);

        $this->laundryService->createOrder($this->hotel, [
            'room_id' => $this->vacantRoom->id,
            'order_type' => LaundryOrderType::GuestValet,
            'is_express' => false,
            'payment_method' => LaundryPaymentMethod::ChargeToRoom,
            'items' => [
                ['laundry_service_id' => $this->shirtService->id, 'quantity' => 1],
            ],
        ], $this->staff);
    }

    public function test_charge_to_room_automatically_posts_to_guest_folio_idempotently(): void
    {
        $order = $this->laundryService->createOrder($this->hotel, [
            'room_id' => $this->occupiedRoom->id,
            'order_type' => LaundryOrderType::GuestValet,
            'is_express' => false,
            'payment_method' => LaundryPaymentMethod::ChargeToRoom,
            'items' => [
                ['laundry_service_id' => $this->shirtService->id, 'quantity' => 2], // 20.00
                ['laundry_service_id' => $this->suitService->id, 'quantity' => 1],  // 20.00
            ],
        ], $this->staff);

        $this->assertEquals('40.00', $order->total_amount);

        // Verify FolioTransaction posted
        Tenant::runAs($this->hotel->id, function () use ($order) {
            $txns = FolioTransaction::where('hotel_id', $this->hotel->id)
                ->where('reference_id', $order->order_number)
                ->where('transaction_type', TransactionType::Laundry)
                ->get();

            $this->assertCount(1, $txns);
            $this->assertEquals('40.00', $txns->first()->amount);
            $this->assertEquals('40.00', $txns->first()->folio->balance);

            // Calling postToFolio again must be idempotent
            $this->laundryService->postToFolio($order, $this->staff);
            $this->assertEquals(1, FolioTransaction::where('reference_id', $order->order_number)->count());
        });
    }

    public function test_pipeline_transitions_and_delivered_lock(): void
    {
        $order = $this->laundryService->createOrder($this->hotel, [
            'order_type' => LaundryOrderType::WalkIn,
            'is_express' => false,
            'payment_method' => LaundryPaymentMethod::Cash,
            'items' => [
                ['laundry_service_id' => $this->shirtService->id, 'quantity' => 1],
            ],
        ], $this->staff);

        $this->assertEquals(LaundryOrderStatus::Received, $order->status);

        $order = $this->laundryService->updateStatus($order, LaundryOrderStatus::InWash);
        $this->assertEquals(LaundryOrderStatus::InWash, $order->status);

        $order = $this->laundryService->updateStatus($order, LaundryOrderStatus::Ironing);
        $this->assertEquals(LaundryOrderStatus::Ironing, $order->status);

        $order = $this->laundryService->updateStatus($order, LaundryOrderStatus::Ready);
        $this->assertEquals(LaundryOrderStatus::Ready, $order->status);

        $order = $this->laundryService->updateStatus($order, LaundryOrderStatus::Delivered);
        $this->assertEquals(LaundryOrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);

        // Delivered order cannot modify items
        $this->expectException(ValidationException::class);
        $this->laundryService->updateOrderItems($order, [
            ['laundry_service_id' => $this->suitService->id, 'quantity' => 1],
        ]);
    }
}
