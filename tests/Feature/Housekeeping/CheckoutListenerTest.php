<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Events\GuestCheckedOutEvent;
use App\Listeners\CreateHousekeepingTaskOnCheckout;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\HousekeepingService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CheckoutListenerTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $manager;

    protected RoomType $roomType;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Standard King',
                'slug' => 'standard-king',
                'base_occupancy' => 2,
                'max_occupancy' => 3,
                'base_price' => '120.00',
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '201',
                'floor_number' => 2,
                'status' => RoomStatus::VacantClean,
                'is_active' => true,
            ]);
        });
    }

    public function test_guest_checkout_creates_normal_housekeeping_task_when_no_same_day_arrival(): void
    {
        $reservation = Tenant::runAs($this->hotel->id, function () {
            $guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Abebe',
                'last_name' => 'Tadesse',
                'phone' => '+251911223344',
                'id_type' => 'passport',
                'id_number' => 'P123456',
            ]);

            $res = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-90001',
                'guest_id' => $guest->id,
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->subDays(2)->toDateString(),
                'check_out_date' => now()->toDateString(),
                'adults_count' => 1,
                'daily_rate' => '120.00',
                'total_amount' => '240.00',
                'status' => ReservationStatus::Confirmed,
                'source' => ReservationSource::WalkIn,
            ]);

            return app(CheckInService::class)->checkIn($res, $this->room->id);
        });

        app(CheckOutService::class)->checkOut($reservation, true, $this->manager);

        Tenant::runAs($this->hotel->id, function () use ($reservation) {
            $this->room->refresh();
            $this->assertEquals(RoomStatus::VacantDirty, $this->room->status);

            $tasks = HousekeepingTask::where('reservation_id', $reservation->id)->get();
            $this->assertCount(1, $tasks);
            $this->assertEquals(HousekeepingTaskType::CheckoutClean, $tasks->first()->task_type);
            $this->assertEquals(HousekeepingPriority::Normal, $tasks->first()->priority);
            $this->assertEquals(HousekeepingTaskStatus::Pending, $tasks->first()->status);
        });
    }

    public function test_guest_checkout_sets_urgent_priority_when_same_day_arrival_is_scheduled(): void
    {
        $departing = Tenant::runAs($this->hotel->id, function () {
            $guest1 = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Dawit',
                'last_name' => 'Mekonnen',
                'phone' => '+251911000001',
                'id_type' => 'national_id',
                'id_number' => 'NID-1',
            ]);

            $guest2 = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Sara',
                'last_name' => 'Alemu',
                'phone' => '+251911000002',
                'id_type' => 'national_id',
                'id_number' => 'NID-2',
            ]);

            $res1 = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-90002',
                'guest_id' => $guest1->id,
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->toDateString(),
                'adults_count' => 1,
                'daily_rate' => '120.00',
                'total_amount' => '120.00',
                'status' => ReservationStatus::Confirmed,
                'source' => ReservationSource::WalkIn,
            ]);

            $checkedIn = app(CheckInService::class)->checkIn($res1, $this->room->id);

            // Back-to-back reservation arriving today in the same room
            Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-90003',
                'guest_id' => $guest2->id,
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'adults_count' => 1,
                'daily_rate' => '120.00',
                'total_amount' => '240.00',
                'status' => ReservationStatus::Confirmed,
                'source' => ReservationSource::Website,
            ]);

            return $checkedIn;
        });

        app(CheckOutService::class)->checkOut($departing, true, $this->manager);

        Tenant::runAs($this->hotel->id, function () use ($departing) {
            $task = HousekeepingTask::where('reservation_id', $departing->id)->first();
            $this->assertNotNull($task);
            $this->assertEquals(HousekeepingPriority::Urgent, $task->priority);
        });
    }

    public function test_listener_is_idempotent_and_task_lifecycle_syncs_room_status(): void
    {
        $hkService = app(HousekeepingService::class);

        $task = Tenant::runAs($this->hotel->id, function () {
            $this->room->markAsDirty();

            $guest = Guest::create([
                'hotel_id' => $this->hotel->id,
                'first_name' => 'Yonas',
                'last_name' => 'Girma',
                'phone' => '+251911000003',
                'id_type' => 'passport',
                'id_number' => 'P999',
            ]);

            $res = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'reservation_code' => 'ENK-2026-90004',
                'guest_id' => $guest->id,
                'room_type_id' => $this->roomType->id,
                'room_id' => $this->room->id,
                'check_in_date' => now()->subDay()->toDateString(),
                'check_out_date' => now()->toDateString(),
                'adults_count' => 1,
                'daily_rate' => '120.00',
                'total_amount' => '120.00',
                'status' => ReservationStatus::CheckedOut,
                'source' => ReservationSource::WalkIn,
            ]);

            $listener = app(CreateHousekeepingTaskOnCheckout::class);
            $listener->handle(new GuestCheckedOutEvent($res));
            $listener->handle(new GuestCheckedOutEvent($res));

            $this->assertEquals(1, HousekeepingTask::where('reservation_id', $res->id)->count());

            return HousekeepingTask::where('reservation_id', $res->id)->firstOrFail();
        });

        // Pending -> InProgress sets Room to Cleaning
        $hkService->updateStatus($task, HousekeepingTaskStatus::InProgress, $this->manager);
        $this->assertEquals(RoomStatus::Cleaning, $this->room->fresh()->status);

        // InProgress -> Inspected -> Pending (rejection) returns Room to VacantDirty
        $hkService->updateStatus($task->fresh(), HousekeepingTaskStatus::Inspected, $this->manager);
        $hkService->updateStatus($task->fresh(), HousekeepingTaskStatus::Pending, $this->manager, 'Mirror streaks');
        $this->assertEquals(RoomStatus::VacantDirty, $this->room->fresh()->status);

        // Pending -> InProgress -> Completed marks Room VacantClean
        $hkService->updateStatus($task->fresh(), HousekeepingTaskStatus::InProgress, $this->manager);
        $hkService->updateStatus($task->fresh(), HousekeepingTaskStatus::Completed, $this->manager);
        $this->assertEquals(RoomStatus::VacantClean, $this->room->fresh()->status);
    }
}
