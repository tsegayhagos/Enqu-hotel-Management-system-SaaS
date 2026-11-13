<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\MaintenanceService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class MaintenanceBlockingTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $manager;

    protected RoomType $roomType;

    protected Room $room;

    protected MaintenanceService $maintenanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
        $this->maintenanceService = app(MaintenanceService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Executive Suite',
                'slug' => 'executive-suite',
                'base_occupancy' => 2,
                'max_occupancy' => 4,
                'base_price' => '250.00',
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '305',
                'floor_number' => 3,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);
        });
    }

    public function test_blocking_ticket_puts_room_out_of_order_and_blocks_check_in_until_resolved(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('pipe_leak.jpg');

        $ticket = $this->maintenanceService->createTicket(
            hotel: $this->hotel,
            data: [
                'room_id' => $this->room->id,
                'title' => 'Burst bathroom supply valve',
                'description' => 'Water flooding bathroom floor.',
                'category' => MaintenanceCategory::Plumbing->value,
                'priority' => MaintenancePriority::High->value,
                'blocks_room' => true,
                'estimated_cost' => 150.00,
            ],
            reporter: $this->manager,
            uploadedPhotos: [$photo]
        );

        $this->assertEquals(RoomStatus::OutOfOrder, $this->room->fresh()->status);
        $this->assertCount(1, $ticket->photos);
        Storage::disk('public')->assertExists($ticket->photos->first()->photo_path);

        // Front desk attempts to check a guest into Room 305 -> MUST be rejected
        $reservation = Tenant::runAs($this->hotel->id, function () {
            $guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Hanna',
                'last_name' => 'Tesfaye',
                'phone' => '+251911555666',
                'id_type' => 'passport',
                'id_number' => 'P555666',
            ]);

            return Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-91001',
                'guest_id' => $guest->id,
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'adults_count' => 1,
                'daily_rate' => '250.00',
                'total_amount' => '500.00',
                'status' => ReservationStatus::Confirmed,
                'source' => ReservationSource::WalkIn,
            ]);
        });

        $checkInFailed = false;
        try {
            app(CheckInService::class)->checkIn($reservation, $this->room->id);
        } catch (ValidationException) {
            $checkInFailed = true;
        }
        $this->assertTrue($checkInFailed, 'Check-in must be rejected while room has an active blocking maintenance ticket.');

        // Create second blocking ticket -> resolving first must KEEP room Out of Order
        $ticket2 = $this->maintenanceService->createTicket(
            hotel: $this->hotel,
            data: [
                'room_id' => $this->room->id,
                'title' => 'Broken AC compressor',
                'description' => 'Zero cooling.',
                'category' => MaintenanceCategory::AcHvac->value,
                'priority' => MaintenancePriority::Emergency->value,
                'blocks_room' => true,
            ],
            reporter: $this->manager
        );

        $this->maintenanceService->resolveTicket($ticket, 'Replaced copper valve', 140.00);
        $this->assertEquals(RoomStatus::OutOfOrder, $this->room->fresh()->status);

        // Resolving second ticket releases room back to VacantDirty for housekeeping
        $resolved2 = $this->maintenanceService->resolveTicket($ticket2, 'Recharged refrigerant and replaced capacitor', 220.00);
        $this->assertEquals(MaintenanceTicketStatus::Resolved, $resolved2->status);
        $this->assertNotNull($resolved2->resolved_at);
        $this->assertEquals(RoomStatus::VacantDirty, $this->room->fresh()->status);
    }

    public function test_occupied_room_cannot_be_forced_out_of_order_directly(): void
    {
        Tenant::runAs($this->hotel->id, fn () => $this->room->setOccupied());

        $this->expectException(ValidationException::class);

        $this->maintenanceService->createTicket(
            hotel: $this->hotel,
            data: [
                'room_id' => $this->room->id,
                'title' => 'Window latch broken',
                'description' => 'Occupied room repair',
                'category' => MaintenanceCategory::Structural->value,
                'priority' => MaintenancePriority::High->value,
                'blocks_room' => true,
            ],
            reporter: $this->manager
        );
    }
}
