<?php

namespace Tests\Feature\FrontDesk;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FolioService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FrontDeskWebTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $staff;

    protected RoomType $roomType;

    protected Room $room;

    protected Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->staff = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Classic Room',
                'slug' => 'classic-room',
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'base_price' => '100.00',
                'is_active' => true,
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '401',
                'floor_number' => 4,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);

            $this->guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Marta',
                'last_name' => 'Girma',
                'phone' => '+251911444555',
                'id_type' => 'passport',
                'id_number' => 'EP444555',
            ]);
        });
    }

    public function test_can_view_front_desk_dashboard(): void
    {
        $response = $this->actingAsIn($this->staff, $this->hotel)->get('/front-desk');
        $response->assertOk();
    }

    public function test_guest_search_endpoint_returns_json(): void
    {
        $response = $this->actingAsIn($this->staff, $this->hotel)
            ->getJson('/guests/search?query=Marta');

        $response->assertOk();
        $response->assertJsonFragment(['first_name' => 'Marta']);
    }

    public function test_can_process_checkin_via_controller(): void
    {
        $reservation = Tenant::runAs($this->hotel->id, fn () => Reservation::create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'ENK-2026-77771',
            'guest_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'daily_rate' => '100.00',
            'total_amount' => '200.00',
            'status' => ReservationStatus::Confirmed,
        ]));

        $response = $this->actingAsIn($this->staff, $this->hotel)->post('/check-in', [
            'reservation_id' => $reservation->id,
            'room_id' => $this->room->id,
            'keycard_number' => 'KEY-401',
            'deposit_amount' => 50,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertEquals(ReservationStatus::CheckedIn, $reservation->fresh()->status);
        $this->assertEquals(RoomStatus::Occupied, $this->room->fresh()->status);
    }

    public function test_can_process_checkout_with_on_the_spot_settlement(): void
    {
        // Setup checked in reservation
        $reservation = Tenant::runAs($this->hotel->id, fn () => Reservation::create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'ENK-2026-77772',
            'guest_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->subDay()->toDateString(),
            'check_out_date' => now()->toDateString(),
            'daily_rate' => '100.00',
            'total_amount' => '100.00',
            'status' => ReservationStatus::Confirmed,
        ]));

        $this->actingAsIn($this->staff, $this->hotel)->post('/check-in', [
            'reservation_id' => $reservation->id,
            'room_id' => $this->room->id,
        ]);

        $res = $reservation->fresh();
        $folio = $res->activeFolio;

        // Post charge
        Tenant::runAs($this->hotel->id, fn () => app(FolioService::class)->addCharge(
            $folio,
            TransactionType::RoomCharge,
            100.00,
            'Stay charge'
        ));

        // Folio balance is 100.00. Check out with on-the-spot settlement of 100.00
        $response = $this->actingAsIn($this->staff, $this->hotel)->post("/check-out/{$res->id}", [
            'settle_balance' => true,
            'settlement_amount' => 100.00,
            'payment_method' => 'cash',
            'confirm_keycard_returned' => true,
        ]);

        $response->assertRedirect();
        $this->assertEquals(ReservationStatus::CheckedOut, $res->fresh()->status);
        $this->assertEquals(RoomStatus::VacantDirty, $this->room->fresh()->status);
        $this->assertEquals('0.00', $folio->fresh()->balance);
    }
}
