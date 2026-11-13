<?php

namespace Tests\Feature\Housekeeping;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HousekeepingWebTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $attendant;

    protected User $manager;

    protected Room $room;

    protected HousekeepingTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->attendant = $this->makeStaff($this->hotel, SystemRole::Housekeeping);
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);

        Tenant::runAs($this->hotel->id, function () {
            $roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Twin Deluxe',
                'slug' => 'twin-deluxe',
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'base_price' => '130.00',
            ]);

            $this->room = Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $roomType->id,
                'room_number' => '108',
                'floor_number' => 1,
                'status' => RoomStatus::VacantDirty,
                'is_active' => true,
            ]);

            $this->task = HousekeepingTask::create([
                'hotel_id' => $this->hotel->id,
                'room_id' => $this->room->id,
                'assigned_to_user_id' => $this->attendant->id,
                'task_type' => HousekeepingTaskType::CheckoutClean,
                'priority' => HousekeepingPriority::Normal,
                'status' => HousekeepingTaskStatus::Pending,
                'due_date' => today()->toDateString(),
            ]);
        });
    }

    public function test_attendant_and_supervisor_full_web_workflow(): void
    {
        // Attendant loads mobile view
        $this->actingAsIn($this->attendant, $this->hotel)
            ->get('/housekeeping/my-tasks')
            ->assertOk();

        // Attendant starts cleaning -> Ready for inspection
        $this->actingAsIn($this->attendant, $this->hotel)
            ->patch("/housekeeping/tasks/{$this->task->id}/status", [
                'status' => HousekeepingTaskStatus::InProgress->value,
            ])
            ->assertRedirect();

        $this->assertEquals(RoomStatus::Cleaning, $this->room->fresh()->status);

        $this->actingAsIn($this->attendant, $this->hotel)
            ->patch("/housekeeping/tasks/{$this->task->id}/status", [
                'status' => HousekeepingTaskStatus::Inspected->value,
            ])
            ->assertRedirect();

        // Attendant CANNOT mark final Completed (only supervisor can)
        $this->actingAsIn($this->attendant, $this->hotel)
            ->patch("/housekeeping/tasks/{$this->task->id}/status", [
                'status' => HousekeepingTaskStatus::Completed->value,
            ])
            ->assertForbidden();

        // Manager opens supervisor floor board and approves task
        $this->actingAsIn($this->manager, $this->hotel)
            ->get('/housekeeping/supervisor')
            ->assertOk();

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch("/housekeeping/tasks/{$this->task->id}/status", [
                'status' => HousekeepingTaskStatus::Completed->value,
            ])
            ->assertRedirect();

        $this->assertEquals(RoomStatus::VacantClean, $this->room->fresh()->status);
    }

    public function test_bulk_assign_and_maintenance_web_resolution(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post('/housekeeping/tasks/bulk-assign', [
                'task_ids' => [$this->task->id],
                'assigned_to_user_id' => $this->attendant->id,
            ])
            ->assertRedirect();

        // Attendant reports blocking maintenance issue via web route
        $this->actingAsIn($this->attendant, $this->hotel)
            ->post('/maintenance/tickets', [
                'room_id' => $this->room->id,
                'title' => 'Loose power outlet',
                'description' => 'Sparking socket next to desk.',
                'category' => MaintenanceCategory::Electrical->value,
                'priority' => MaintenancePriority::Emergency->value,
                'blocks_room' => true,
            ])
            ->assertRedirect();

        $ticket = Tenant::runAs($this->hotel->id, fn () => MaintenanceTicket::firstOrFail());
        $this->assertEquals(RoomStatus::OutOfOrder, $this->room->fresh()->status);

        // Manager views /maintenance and resolves the ticket
        $this->actingAsIn($this->manager, $this->hotel)
            ->get('/maintenance')
            ->assertOk();

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch("/maintenance/tickets/{$ticket->id}/resolve", [
                'resolution_notes' => 'Replaced 16A wall receptacle and tested breaker.',
                'actual_cost' => 35.00,
            ])
            ->assertRedirect();

        $this->assertEquals(MaintenanceTicketStatus::Resolved, $ticket->fresh()->status);
        $this->assertEquals(RoomStatus::VacantDirty, $this->room->fresh()->status);
    }

    public function test_other_tenant_cannot_touch_housekeeping_or_maintenance_records(): void
    {
        $otherHotel = $this->makeHotel();
        $otherManager = $this->makeStaff($otherHotel, SystemRole::HotelManager);

        $this->actingAsIn($otherManager, $otherHotel)
            ->patch("/housekeeping/tasks/{$this->task->id}/status", [
                'status' => HousekeepingTaskStatus::InProgress->value,
            ])
            ->assertNotFound();
    }
}
