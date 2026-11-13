<?php

namespace Tests\Feature\Billing;

use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $cashier;

    protected FolioService $folioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->cashier = $this->makeStaff($this->hotel, SystemRole::PosCashier);
        $this->folioService = app(FolioService::class);
    }

    public function test_pos_can_fetch_active_occupied_rooms(): void
    {
        Sanctum::actingAs($this->cashier);

        $roomType = $this->makeRoomType($this->hotel);

        $occupiedRoom = $this->makeRoom($this->hotel, [
            'room_number' => '101',
            'status' => RoomStatus::Occupied,
        ], $roomType);

        $vacantRoom = $this->makeRoom($this->hotel, [
            'room_number' => '102',
            'status' => RoomStatus::VacantClean,
        ], $roomType);

        $this->folioService->openFolio($this->hotel, [
            'room_id' => $occupiedRoom->id,
            'guest_name' => 'Abebe Guest',
        ]);

        $response = $this->withSession([Tenant::SESSION_KEY => $this->hotel->id])
            ->getJson('/api/pos/active-rooms');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('rooms.0.room_number', '101')
            ->assertJsonPath('rooms.0.guest_name', 'Abebe Guest');
    }

    public function test_pos_can_post_charge_to_room_folio(): void
    {
        Sanctum::actingAs($this->cashier);

        $roomType = $this->makeRoomType($this->hotel);

        $room = $this->makeRoom($this->hotel, [
            'room_number' => '201',
            'status' => RoomStatus::Occupied,
        ], $roomType);

        $folio = $this->folioService->openFolio($this->hotel, [
            'room_id' => $room->id,
            'guest_name' => 'Sara Dinner',
        ]);

        $response = $this->withSession([Tenant::SESSION_KEY => $this->hotel->id])
            ->postJson('/api/pos/charge-to-room', [
                'room_id' => $room->id,
                'amount' => '42.50',
                'description' => 'Sky Lounge Bar - Cocktail & Snacks',
                'reference_id' => 'BAR-0091',
                'service_type' => 'restaurant_pos',
            ]);

        $response->assertCreated()
            ->assertJsonPath('folio.balance', '42.50')
            ->assertJsonPath('transaction.description', 'Sky Lounge Bar - Cocktail & Snacks');

        $folio->refresh();
        $this->assertEquals('42.50', $folio->balance);
        $this->assertEquals('42.50', $folio->total_charges);
    }

    public function test_pos_charge_fails_for_non_occupied_or_missing_room(): void
    {
        Sanctum::actingAs($this->cashier);

        $response = $this->withSession([Tenant::SESSION_KEY => $this->hotel->id])
            ->postJson('/api/pos/charge-to-room', [
                'room_id' => 99999,
                'amount' => '20.00',
                'description' => 'Bar item',
            ]);

        $response->assertStatus(422);
    }
}
