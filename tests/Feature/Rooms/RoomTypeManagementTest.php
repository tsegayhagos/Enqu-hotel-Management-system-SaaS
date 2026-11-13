<?php

namespace Tests\Feature\Rooms;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RoomTypeManagementTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Deluxe Room',
            'description' => 'Spacious',
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'base_price' => '120.50',
            'extra_person_fee' => '20.00',
            'amenities' => ['Wi-Fi', ' Mini Bar ', 'Wi-Fi', ''],
        ];
    }

    private function fresh(RoomType $type): ?RoomType
    {
        return Tenant::runAs($this->hotel->id, fn () => $type->fresh());
    }

    public function test_manager_creates_a_room_type_with_slug_and_clean_amenities(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('room-types.store'), $this->payload())
            ->assertRedirect(route('room-types.index'))
            ->assertSessionHasNoErrors();

        $type = RoomType::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->firstOrFail();

        $this->assertSame('deluxe-room', $type->slug);
        $this->assertSame('120.50', $type->base_price);
        $this->assertSame(['Wi-Fi', 'Mini Bar'], $type->amenities);
        $this->assertSame($this->hotel->id, $type->hotel_id);
    }

    public function test_duplicate_names_are_rejected_within_a_hotel_but_allowed_across_hotels(): void
    {
        $this->makeRoomType($this->hotel, ['name' => 'Deluxe Room']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('room-types.store'), $this->payload())
            ->assertSessionHasErrors('name');

        $other = $this->makeHotel();
        $otherManager = $this->makeStaff($other, SystemRole::HotelManager);

        $this->actingAsIn($otherManager, $other)
            ->post(route('room-types.store'), $this->payload())
            ->assertSessionHasNoErrors();
    }

    public function test_occupancy_and_price_rules(): void
    {
        $as = fn (array $overrides) => $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('room-types.store'), $this->payload($overrides));

        $as(['max_occupancy' => 1, 'base_occupancy' => 3])->assertSessionHasErrors('max_occupancy');
        $as(['base_price' => '12.345'])->assertSessionHasErrors('base_price');
        $as(['base_price' => '-5'])->assertSessionHasErrors('base_price');
        $as(['base_price' => 'abc'])->assertSessionHasErrors('base_price');
        $as(['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_manager_updates_a_room_type_and_may_keep_its_own_name(): void
    {
        $type = $this->makeRoomType($this->hotel, ['name' => 'Deluxe Room', 'slug' => 'deluxe-room']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('room-types.update', $type), $this->payload(['base_price' => '199.99']))
            ->assertRedirect(route('room-types.index'))
            ->assertSessionHasNoErrors();

        $fresh = $this->fresh($type);

        $this->assertSame('199.99', $fresh->base_price);
        $this->assertSame('deluxe-room', $fresh->slug, 'The slug must stay stable when a type is edited.');
    }

    public function test_a_type_with_rooms_cannot_be_deleted(): void
    {
        $type = $this->makeRoomType($this->hotel);
        $this->makeRoom($this->hotel, [], $type);

        $this->actingAsIn($this->manager, $this->hotel)
            ->delete(route('room-types.destroy', $type))
            ->assertSessionHasErrors('room_type');

        $this->assertNotNull($this->fresh($type));
    }

    public function test_an_empty_type_can_be_deleted(): void
    {
        $type = $this->makeRoomType($this->hotel);

        $this->actingAsIn($this->manager, $this->hotel)
            ->delete(route('room-types.destroy', $type))
            ->assertRedirect(route('room-types.index'));

        $this->assertNull($this->fresh($type));
    }

    public function test_index_shows_room_counts(): void
    {
        $type = $this->makeRoomType($this->hotel, ['name' => 'Standard']);
        $this->makeRoom($this->hotel, ['room_number' => '1'], $type);
        $this->makeRoom($this->hotel, ['room_number' => '2'], $type);

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('room-types.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('RoomTypes/Index')
                ->has('roomTypes', 1)
                ->where('roomTypes.0.rooms_count', 2));
    }

    public function test_front_desk_cannot_manage_room_types(): void
    {
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->actingAsIn($desk, $this->hotel)->get(route('room-types.index'))->assertForbidden();
        $this->actingAsIn($desk, $this->hotel)->post(route('room-types.store'), $this->payload())->assertForbidden();
    }

    public function test_another_hotels_room_types_are_invisible_and_untouchable(): void
    {
        $foreign = $this->makeRoomType($this->makeHotel(), ['name' => 'Secret Suite']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('room-types.index'))
            ->assertInertia(fn (Assert $page) => $page->has('roomTypes', 0));

        $this->actingAsIn($this->manager, $this->hotel)->put(route('room-types.update', $foreign->id), $this->payload())->assertNotFound();
        $this->actingAsIn($this->manager, $this->hotel)->delete(route('room-types.destroy', $foreign->id))->assertNotFound();
    }
}
