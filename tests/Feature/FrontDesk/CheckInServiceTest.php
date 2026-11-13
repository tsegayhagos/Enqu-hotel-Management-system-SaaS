<?php

namespace Tests\Feature\FrontDesk;

use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\CheckInService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CheckInServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected RoomType $roomType;

    protected Room $room;

    protected Guest $guest;

    protected CheckInService $checkInService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->checkInService = app(CheckInService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room',
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'base_price' => '150.00',
                'is_active' => true,
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '101',
                'floor_number' => 1,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);

            $this->guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Sara',
                'last_name' => 'Tesfaye',
                'phone' => '+251911000000',
                'id_type' => 'passport',
                'id_number' => 'EP123456',
                'nationality' => 'Ethiopian',
            ]);
        });
    }

    public function test_can_check_in_confirmed_reservation(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-99991',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'daily_rate' => '150.00',
                'total_amount' => '300.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $checkedIn = $this->checkInService->checkIn(
                reservation: $reservation,
                roomId: $this->room->id,
                keycardNumber: 'KEY-001',
                depositAmount: 50.00,
                paymentMethod: PaymentMethod::Cash
            );

            $this->assertEquals(ReservationStatus::CheckedIn, $checkedIn->status);
            $this->assertEquals('KEY-001', $checkedIn->keycard_number);
            $this->assertNotNull($checkedIn->actual_check_in_at);

            // Room status must be Occupied
            $this->room->refresh();
            $this->assertEquals(RoomStatus::Occupied, $this->room->status);

            // Folio must be opened and deposit posted
            $folio = $checkedIn->activeFolio;
            $this->assertNotNull($folio);
            $this->assertEquals(FolioStatus::Open, $folio->status);
            $this->assertEquals('50.00', $folio->total_payments);
            $this->assertEquals('-50.00', $folio->balance); // 50 credit
        });
    }

    public function test_cannot_check_in_if_room_is_dirty(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $this->room->status = RoomStatus::VacantDirty;
            $this->room->save();

            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-99992',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(1)->toDateString(),
                'daily_rate' => '150.00',
                'total_amount' => '150.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $this->expectException(ValidationException::class);
            $this->checkInService->checkIn($reservation, $this->room->id);
        });
    }

    public function test_blacklisted_guest_check_in_prevented_without_override(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $this->guest->is_blacklisted = true;
            $this->guest->blacklist_reason = 'Disorderly conduct';
            $this->guest->save();

            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-99993',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(1)->toDateString(),
                'daily_rate' => '150.00',
                'total_amount' => '150.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $this->expectException(ValidationException::class);
            $this->checkInService->checkIn($reservation, $this->room->id, managerOverrideBlacklist: false);
        });
    }

    public function test_blacklisted_guest_check_in_allowed_with_manager_override(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $this->guest->is_blacklisted = true;
            $this->guest->blacklist_reason = 'Previous debt resolved';
            $this->guest->save();

            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-99994',
                'guest_id' => $this->guest->id,
                'room_id' => $this->room->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(1)->toDateString(),
                'daily_rate' => '150.00',
                'total_amount' => '150.00',
                'status' => ReservationStatus::Confirmed,
            ]);

            $checkedIn = $this->checkInService->checkIn($reservation, $this->room->id, managerOverrideBlacklist: true);
            $this->assertEquals(ReservationStatus::CheckedIn, $checkedIn->status);
        });
    }

    public function test_walk_in_check_in_creates_guest_reservation_and_occupies_room(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $guestData = [
                'first_name' => 'Walkin',
                'last_name' => 'User',
                'phone' => '+251955667788',
                'id_type' => 'national_id',
                'id_number' => 'NID-556677',
            ];

            $stayData = [
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(1)->toDateString(),
                'daily_rate' => '150.00',
                'keycard_number' => 'KEY-W01',
                'deposit_amount' => 100.00,
                'payment_method' => PaymentMethod::Cash,
            ];

            $res = $this->checkInService->checkInWalkIn($this->hotel, $guestData, $stayData);

            $this->assertEquals(ReservationStatus::CheckedIn, $res->status);
            $this->assertEquals('KEY-W01', $res->keycard_number);

            $this->room->refresh();
            $this->assertEquals(RoomStatus::Occupied, $this->room->status);

            $this->assertDatabaseHas('guests', [
                'hotel_id' => $this->hotel->id,
                'phone' => '+251955667788',
            ]);
        });
    }
}
