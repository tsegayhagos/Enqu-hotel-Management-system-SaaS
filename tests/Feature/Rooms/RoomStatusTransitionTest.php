<?php

namespace Tests\Feature\Rooms;

use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Exceptions\InvalidRoomTransition;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RoomStatusTransitionTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private Hotel $hotel;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
    }

    private function room(RoomStatus $status): Room
    {
        return $this->makeRoom($this->hotel, ['status' => $status]);
    }

    private function in(callable $callback): mixed
    {
        return Tenant::runAs($this->hotel->id, $callback);
    }

    // ---- Model state machine -------------------------------------------------------------------

    public function test_full_guest_cycle(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->in(function () use ($room): void {
            $room->setOccupied();
            $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);

            $room->markAsDirty();
            $this->assertSame(RoomStatus::VacantDirty, $room->fresh()->status);

            $room->startCleaning();
            $this->assertSame(RoomStatus::Cleaning, $room->fresh()->status);

            $room->markAsClean();
            $this->assertSame(RoomStatus::VacantClean, $room->fresh()->status);
        });
    }

    public function test_clean_to_clean_is_rejected(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->expectException(InvalidRoomTransition::class);

        $this->in(fn () => $room->markAsClean());
    }

    public function test_a_dirty_room_cannot_be_occupied(): void
    {
        $room = $this->room(RoomStatus::VacantDirty);

        $this->expectException(InvalidRoomTransition::class);

        $this->in(fn () => $room->setOccupied());
    }

    public function test_a_cleaning_room_cannot_be_occupied(): void
    {
        $room = $this->room(RoomStatus::Cleaning);

        $this->expectException(InvalidRoomTransition::class);

        $this->in(fn () => $room->setOccupied());
    }

    public function test_an_occupied_room_cannot_be_blocked(): void
    {
        $room = $this->room(RoomStatus::Occupied);

        $this->expectException(InvalidRoomTransition::class);

        $this->in(fn () => $room->setOutOfOrder());
    }

    public function test_out_of_order_returns_to_dirty_never_straight_to_clean(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->in(function () use ($room): void {
            $room->setOutOfOrder();

            try {
                $room->markAsClean();
                $this->fail('Out of order must not jump to vacant clean.');
            } catch (InvalidRoomTransition) {
                // expected
            }

            $room->markAsDirty();
            $this->assertSame(RoomStatus::VacantDirty, $room->fresh()->status);
        });
    }

    public function test_manual_changes_cannot_touch_occupancy(): void
    {
        $clean = $this->room(RoomStatus::VacantClean);
        $occupied = $this->room(RoomStatus::Occupied);

        $this->in(function () use ($clean, $occupied): void {
            try {
                $clean->transitionTo(RoomStatus::Occupied, manual: true);
                $this->fail('A person must not set Occupied by hand.');
            } catch (InvalidRoomTransition) {
                // expected
            }

            try {
                $occupied->transitionTo(RoomStatus::VacantDirty, manual: true);
                $this->fail('A person must not free an occupied room by hand.');
            } catch (InvalidRoomTransition) {
                // expected
            }
        });

        $this->assertSame(RoomStatus::VacantClean, Tenant::runAs($this->hotel->id, fn () => $clean->fresh()->status));
    }

    public function test_enum_transition_table(): void
    {
        foreach (RoomStatus::cases() as $status) {
            $this->assertFalse($status->canTransitionTo($status), "{$status->value} -> itself must be invalid");
        }

        $this->assertTrue(RoomStatus::VacantClean->canTransitionTo(RoomStatus::Occupied));
        $this->assertTrue(RoomStatus::Occupied->canTransitionTo(RoomStatus::VacantDirty));
        $this->assertFalse(RoomStatus::Occupied->canTransitionTo(RoomStatus::VacantClean));
        $this->assertFalse(RoomStatus::OutOfOrder->isSellable());
        $this->assertTrue(RoomStatus::Cleaning->isSellable());
    }

    // ---- HTTP endpoint -------------------------------------------------------------------------

    public function test_manager_changes_a_room_status(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'vacant_dirty'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(RoomStatus::VacantDirty, $this->in(fn () => $room->fresh()->status));
    }

    public function test_invalid_hop_is_rejected_with_a_clear_error(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'vacant_clean'])
            ->assertSessionHasErrors('status');

        $this->assertSame(RoomStatus::VacantClean, $this->in(fn () => $room->fresh()->status));
    }

    public function test_occupied_cannot_be_set_through_the_endpoint(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'occupied'])
            ->assertSessionHasErrors('status');
    }

    public function test_an_occupied_room_cannot_be_changed_through_the_endpoint(): void
    {
        $room = $this->room(RoomStatus::Occupied);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'vacant_dirty'])
            ->assertSessionHasErrors('status');

        $this->assertSame(RoomStatus::Occupied, $this->in(fn () => $room->fresh()->status));
    }

    public function test_unknown_status_is_rejected(): void
    {
        $room = $this->room(RoomStatus::VacantClean);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'haunted'])
            ->assertSessionHasErrors('status');
    }

    public function test_front_desk_cannot_change_status_manually(): void
    {
        $room = $this->room(RoomStatus::VacantClean);
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->actingAsIn($desk, $this->hotel)
            ->patch(route('rooms.status', $room), ['status' => 'vacant_dirty'])
            ->assertForbidden();
    }

    public function test_a_room_of_another_hotel_is_not_found(): void
    {
        $other = $this->makeHotel();
        $foreign = $this->makeRoom($other, ['status' => RoomStatus::VacantClean]);

        $this->actingAsIn($this->manager, $this->hotel)
            ->patch(route('rooms.status', $foreign->id), ['status' => 'vacant_dirty'])
            ->assertNotFound();

        $this->assertSame(RoomStatus::VacantClean, Tenant::runAs($other->id, fn () => $foreign->fresh()->status));
    }
}
