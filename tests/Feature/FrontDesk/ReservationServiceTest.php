<?php

namespace Tests\Feature\FrontDesk;

use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\ReservationCodeGenerator;
use App\Services\ReservationService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected RoomType $roomType;

    protected Room $room;

    protected Guest $guest;

    protected ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->reservationService = app(ReservationService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Suite Room',
                'slug' => 'suite-room',
                'base_occupancy' => 2,
                'max_occupancy' => 4,
                'base_price' => '200.00',
                'is_active' => true,
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '301',
                'floor_number' => 3,
                'status' => 'vacant_clean',
                'is_active' => true,
            ]);

            $this->guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Tadesse',
                'last_name' => 'Kassahun',
                'phone' => '+251912345678',
                'id_type' => 'passport',
                'id_number' => 'EP777777',
            ]);
        });
    }

    public function test_generates_unique_code(): void
    {
        $generator = app(ReservationCodeGenerator::class);
        $code = $generator->generate($this->hotel->id);

        $this->assertMatchesRegularExpression('/^ENK-\d{4}-\d{5}$/', $code);
    }

    public function test_can_create_reservation(): void
    {
        $res = $this->reservationService->createReservation($this->hotel, [
            'guest_id' => $this->guest->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'daily_rate' => 200.00,
        ]);

        $this->assertEquals(ReservationStatus::Confirmed, $res->status);
        $this->assertEquals('600.00', $res->total_amount); // 3 nights * 200
    }

    public function test_prevents_double_booking_on_overlapping_dates(): void
    {
        // 1. Create first reservation for dates 10th to 15th
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(15)->toDateString();

        $this->reservationService->createReservation($this->hotel, [
            'guest_id' => $this->guest->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'daily_rate' => 200.00,
        ]);

        // 2. Attempt second reservation for dates 12th to 17th (overlaps)
        $this->expectException(ValidationException::class);
        $this->reservationService->createReservation($this->hotel, [
            'guest_id' => $this->guest->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(12)->toDateString(),
            'check_out_date' => now()->addDays(17)->toDateString(),
            'daily_rate' => 200.00,
        ]);
    }

    public function test_allows_adjacent_booking(): void
    {
        // Reservation 1: days 5 to 7
        $this->reservationService->createReservation($this->hotel, [
            'guest_id' => $this->guest->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'daily_rate' => 200.00,
        ]);

        // Reservation 2 starts exactly when Reservation 1 departs (day 7 to 9)
        $res2 = $this->reservationService->createReservation($this->hotel, [
            'guest_id' => $this->guest->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'check_in_date' => now()->addDays(7)->toDateString(),
            'check_out_date' => now()->addDays(9)->toDateString(),
            'daily_rate' => 200.00,
        ]);

        $this->assertInstanceOf(Reservation::class, $res2);
    }
}
