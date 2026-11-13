<?php

namespace Tests\Feature\Rooms;

use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BulkRoomCreator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RoomManagementTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private Hotel $hotel;

    private User $manager;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
        $this->type = $this->makeRoomType($this->hotel, ['name' => 'Standard']);
    }

    private function room(string $number, int $floor = 1, RoomStatus $status = RoomStatus::VacantClean, ?RoomType $type = null): Room
    {
        return $this->makeRoom($this->hotel, ['room_number' => $number, 'floor_number' => $floor, 'status' => $status], $type ?? $this->type);
    }

    // ---- Access ---------------------------------------------------------------------------------

    public function test_front_desk_can_view_but_not_manage_rooms(): void
    {
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->actingAsIn($desk, $this->hotel)->get(route('rooms.index'))->assertOk();
        $this->actingAsIn($desk, $this->hotel)->get(route('rooms.create'))->assertForbidden();
        $this->actingAsIn($desk, $this->hotel)
            ->post(route('rooms.store'), ['room_type_id' => $this->type->id, 'room_number' => '1', 'floor_number' => 1])
            ->assertForbidden();
    }

    public function test_roles_without_room_permissions_cannot_view_rooms(): void
    {
        $accountant = $this->makeStaff($this->hotel, SystemRole::Accountant);

        $this->actingAsIn($accountant, $this->hotel)->get(route('rooms.index'))->assertForbidden();
    }

    // ---- Listing & filters ----------------------------------------------------------------------

    public function test_index_lists_rooms_with_counts_and_natural_ordering(): void
    {
        $this->room('10', 1);
        $this->room('2', 1);
        $this->room('201', 2, RoomStatus::Occupied);

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rooms.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rooms/Index')
                ->has('rooms', 3)
                ->where('rooms.0.room_number', '2')
                ->where('rooms.1.room_number', '10')
                ->where('rooms.2.room_number', '201')
                ->where('counts.total', 3)
                ->where('counts.occupied', 1)
                ->where('counts.vacant_clean', 2)
                ->where('counts.out_of_order', 0));
    }

    public function test_filters_by_floor_status_type_and_search(): void
    {
        $deluxe = $this->makeRoomType($this->hotel, ['name' => 'Deluxe']);
        $this->room('101', 1);
        $this->room('102', 1, RoomStatus::VacantDirty);
        $this->room('201', 2, RoomStatus::VacantClean, $deluxe);
        $this->room('202', 2, RoomStatus::VacantDirty, $deluxe);

        $as = fn (array $query) => $this->actingAsIn($this->manager, $this->hotel)->get(route('rooms.index', $query));

        $as(['floor' => 2])->assertInertia(fn (Assert $p) => $p->has('rooms', 2));
        $as(['status' => 'vacant_dirty'])->assertInertia(fn (Assert $p) => $p->has('rooms', 2));
        $as(['room_type_id' => $deluxe->id])->assertInertia(fn (Assert $p) => $p->has('rooms', 2));
        $as(['search' => '10'])->assertInertia(fn (Assert $p) => $p->has('rooms', 2));
        $as(['floor' => 2, 'status' => 'vacant_dirty'])->assertInertia(fn (Assert $p) => $p->has('rooms', 1)->where('rooms.0.room_number', '202'));
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rooms.index', ['status' => 'haunted']))
            ->assertSessionHasErrors('status');
    }

    public function test_rooms_expose_only_manual_status_options(): void
    {
        $this->room('101', 1, RoomStatus::Occupied);
        $this->room('102', 1, RoomStatus::VacantDirty);

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rooms.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rooms.0.allowed_statuses', [])
                ->where('rooms.1.allowed_statuses', ['cleaning', 'vacant_clean', 'out_of_order']));
    }

    // ---- Creating -------------------------------------------------------------------------------

    public function test_manager_creates_a_room_in_their_hotel(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.store'), [
                'room_type_id' => $this->type->id, 'room_number' => '101', 'floor_number' => 1, 'notes' => 'Corner room',
            ])
            ->assertRedirect(route('rooms.index'));

        $room = Room::query()->withoutGlobalScopes()->where('room_number', '101')->firstOrFail();

        $this->assertSame($this->hotel->id, $room->hotel_id);
        $this->assertSame(RoomStatus::VacantClean, $room->status);
    }

    public function test_duplicate_room_numbers_in_the_same_hotel_are_rejected(): void
    {
        $this->room('101');

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.store'), ['room_type_id' => $this->type->id, 'room_number' => '101', 'floor_number' => 2])
            ->assertSessionHasErrors(['room_number' => 'This room number already exists in this hotel.']);

        $this->assertSame(1, Room::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->count());
    }

    public function test_the_same_room_number_can_exist_in_two_hotels(): void
    {
        $other = $this->makeHotel();
        $otherManager = $this->makeStaff($other, SystemRole::HotelManager);
        $otherType = $this->makeRoomType($other);
        $this->room('101');

        $this->actingAsIn($otherManager, $other)
            ->post(route('rooms.store'), ['room_type_id' => $otherType->id, 'room_number' => '101', 'floor_number' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Room::query()->withoutGlobalScopes()->where('room_number', '101')->count());
    }

    public function test_a_room_type_of_another_hotel_cannot_be_used(): void
    {
        $foreignType = $this->makeRoomType($this->makeHotel());

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.store'), ['room_type_id' => $foreignType->id, 'room_number' => '555', 'floor_number' => 1])
            ->assertSessionHasErrors('room_type_id');
    }

    public function test_invalid_room_input_is_rejected(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.store'), ['room_type_id' => $this->type->id, 'room_number' => '<b>1</b>', 'floor_number' => 999])
            ->assertSessionHasErrors(['room_number', 'floor_number']);
    }

    // ---- Updating -------------------------------------------------------------------------------

    public function test_manager_updates_notes_and_can_deactivate_a_vacant_room(): void
    {
        $room = $this->room('101');

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rooms.update', $room), ['notes' => 'Broken lamp', 'is_active' => false])
            ->assertSessionHasNoErrors();

        $fresh = Tenant::runAs($this->hotel->id, fn () => $room->fresh());

        $this->assertSame('Broken lamp', $fresh->notes);
        $this->assertFalse($fresh->is_active);
    }

    public function test_an_occupied_room_cannot_be_deactivated(): void
    {
        $room = $this->room('101', 1, RoomStatus::Occupied);

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rooms.update', $room), ['is_active' => false])
            ->assertSessionHasErrors('is_active');
    }

    public function test_renaming_to_an_existing_number_is_rejected_but_keeping_your_own_is_fine(): void
    {
        $first = $this->room('101');
        $this->room('102');

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rooms.update', $first), ['room_number' => '102'])
            ->assertSessionHasErrors('room_number');

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rooms.update', $first), ['room_number' => '101', 'notes' => 'ok'])
            ->assertSessionHasNoErrors();
    }

    // ---- Tenant isolation -----------------------------------------------------------------------

    public function test_other_hotels_rooms_are_invisible_and_untouchable(): void
    {
        $other = $this->makeHotel();
        $foreign = $this->makeRoom($other, ['room_number' => '999']);
        $this->room('101');

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rooms.index'))
            ->assertInertia(fn (Assert $page) => $page->has('rooms', 1)->where('rooms.0.room_number', '101'));

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rooms.update', $foreign->id), ['notes' => 'hacked'])
            ->assertNotFound();
    }

    // ---- Performance (QA: 200+ rooms) -----------------------------------------------------------

    public function test_grid_with_250_rooms_loads_without_extra_queries(): void
    {
        $this->room('1');

        DB::enableQueryLog();
        $this->actingAsIn($this->manager, $this->hotel)->get(route('rooms.index'))->assertOk();
        $small = count(DB::getQueryLog());

        Tenant::runAs($this->hotel->id, function (): void {
            app(BulkRoomCreator::class)->create($this->type, 2, 1, 250, 'B-');
        });

        DB::flushQueryLog();
        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rooms.index'))
            ->assertInertia(fn (Assert $page) => $page->has('rooms', 251)->where('counts.total', 251));
        $large = count(DB::getQueryLog());

        // No N+1: query count must not grow with the number of rooms.
        $this->assertLessThanOrEqual($small, $large, "Queries grew from {$small} to {$large}");
    }
}
