<?php

namespace Tests\Feature\FrontDesk;

use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Events\GuestCheckedOutEvent;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\FolioService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CheckOutServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected RoomType $roomType;

    protected Room $room;

    protected Guest $guest;

    protected CheckInService $checkInService;

    protected CheckOutService $checkOutService;

    protected FolioService $folioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->checkInService = app(CheckInService::class);
        $this->checkOutService = app(CheckOutService::class);
        $this->folioService = app(FolioService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Standard Room',
                'slug' => 'standard-room',
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'base_price' => '100.00',
                'is_active' => true,
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '201',
                'floor_number' => 2,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);

            $this->guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Dawit',
                'last_name' => 'Yilma',
                'phone' => '+251911999999',
                'id_type' => 'passport',
                'id_number' => 'EP999999',
                'nationality' => 'Ethiopian',
                'total_stays_count' => 2,
            ]);
        });
    }

    public function test_can_check_out_when_balance_is_zero(): void
    {
        Event::fake([GuestCheckedOutEvent::class]);

        Tenant::runAs($this->hotel->id, function () {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-88881',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->toDateString(),
                'daily_rate' => '100.00',
                'total_amount' => '100.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $res = $this->checkInService->checkIn($reservation, $this->room->id);
            $folio = $res->activeFolio;

            // Balance is 0 (charges = 0, payments = 0)
            $checkedOut = $this->checkOutService->checkOut($res);

            $this->assertEquals(ReservationStatus::CheckedOut, $checkedOut->status);
            $this->assertNotNull($checkedOut->actual_check_out_at);

            // Room must be transitioned to Vacant Dirty
            $this->room->refresh();
            $this->assertEquals(RoomStatus::VacantDirty, $this->room->status);

            // Guest stay count must increment
            $this->guest->refresh();
            $this->assertEquals(3, $this->guest->total_stays_count);

            // Folio must be closed
            $folio->refresh();
            $this->assertEquals(FolioStatus::Closed, $folio->status);

            // Event dispatched
            Event::assertDispatched(GuestCheckedOutEvent::class, function ($event) use ($res) {
                return $event->reservation->id === $res->id;
            });
        });
    }

    public function test_cannot_check_out_with_unsettled_balance(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-88882',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->toDateString(),
                'daily_rate' => '100.00',
                'total_amount' => '100.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $res = $this->checkInService->checkIn($reservation, $this->room->id);
            $folio = $res->activeFolio;

            // Add room charge: balance is now 100.00
            $this->folioService->addCharge($folio, TransactionType::RoomCharge, 100.00, 'Room stay');

            $this->expectException(ValidationException::class);
            $this->checkOutService->checkOut($res, managerOverrideBalance: false);
        });
    }

    public function test_can_check_out_with_unsettled_balance_if_manager_override_granted(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-88883',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->toDateString(),
                'daily_rate' => '100.00',
                'total_amount' => '100.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $res = $this->checkInService->checkIn($reservation, $this->room->id);
            $folio = $res->activeFolio;

            $this->folioService->addCharge($folio, TransactionType::RoomCharge, 100.00, 'Room stay');

            $manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
            $checkedOut = $this->checkOutService->checkOut($res, managerOverrideBalance: true, authorizingUser: $manager);
            $this->assertEquals(ReservationStatus::CheckedOut, $checkedOut->status);
        });
    }
}
