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
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class BulkRoomCreationTest extends TestCase
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
        $this->type = $this->makeRoomType($this->hotel);
    }

    private function roomCount(?Hotel $hotel = null): int
    {
        return Room::query()->withoutGlobalScopes()->where('hotel_id', ($hotel ?? $this->hotel)->id)->count();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'room_type_id' => $this->type->id,
            'floor_number' => 1,
            'prefix' => '',
            'start_number' => 101,
            'end_number' => 120,
        ];
    }

    public function test_generates_a_floor_of_rooms(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload())
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $this->roomCount());

        $rooms = Room::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->orderBy('id')->get();

        $this->assertSame('101', $rooms->first()->room_number);
        $this->assertSame('120', $rooms->last()->room_number);
        $this->assertTrue($rooms->every(fn (Room $r): bool => $r->floor_number === 1
            && $r->room_type_id === $this->type->id
            && $r->status === RoomStatus::VacantClean
            && $r->hotel_id === $this->hotel->id));
    }

    public function test_prefix_is_prepended_to_every_number(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['prefix' => 'A-', 'start_number' => 1, 'end_number' => 3, 'floor_number' => 4]))
            ->assertSessionHasNoErrors();

        $numbers = Room::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->orderBy('id')->pluck('room_number')->all();

        $this->assertSame(['A-1', 'A-2', 'A-3'], $numbers);
    }

    public function test_a_single_number_range_creates_one_room(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['start_number' => 7, 'end_number' => 7]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->roomCount());
    }

    public function test_it_is_all_or_nothing_when_any_number_already_exists(): void
    {
        $this->makeRoom($this->hotel, ['room_number' => '105'], $this->type);

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['start_number' => 101, 'end_number' => 110]))
            ->assertSessionHasErrors('start_number');

        $this->assertSame(1, $this->roomCount(), 'Nothing must be created when one number collides.');
    }

    public function test_the_collision_message_names_the_numbers(): void
    {
        $this->makeRoom($this->hotel, ['room_number' => '105'], $this->type);

        try {
            Tenant::runAs($this->hotel->id, fn () => app(BulkRoomCreator::class)->create($this->type, 1, 101, 110));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('105', $e->errors()['start_number'][0]);
        }
    }

    public function test_last_number_cannot_be_lower_than_the_first(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['start_number' => 120, 'end_number' => 101]))
            ->assertSessionHasErrors('end_number');

        $this->assertSame(0, $this->roomCount());
    }

    public function test_more_than_500_rooms_at_once_is_refused(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['start_number' => 1, 'end_number' => 501]))
            ->assertSessionHasErrors('end_number');

        $this->assertSame(0, $this->roomCount());

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['start_number' => 1, 'end_number' => 500]))
            ->assertSessionHasNoErrors();

        $this->assertSame(500, $this->roomCount());
    }

    public function test_a_room_type_of_another_hotel_is_refused(): void
    {
        $foreignType = $this->makeRoomType($this->makeHotel());

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['room_type_id' => $foreignType->id]))
            ->assertSessionHasErrors('room_type_id');

        $this->assertSame(0, $this->roomCount());
    }

    public function test_invalid_prefix_characters_are_refused(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rooms.bulk'), $this->payload(['prefix' => '<script>']))
            ->assertSessionHasErrors('prefix');
    }

    public function test_numbers_may_repeat_across_hotels(): void
    {
        $other = $this->makeHotel();
        $otherManager = $this->makeStaff($other, SystemRole::HotelManager);
        $otherType = $this->makeRoomType($other);

        $this->actingAsIn($this->manager, $this->hotel)->post(route('rooms.bulk'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAsIn($otherManager, $other)
            ->post(route('rooms.bulk'), $this->payload(['room_type_id' => $otherType->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $this->roomCount());
        $this->assertSame(20, $this->roomCount($other));
    }

    public function test_front_desk_cannot_generate_rooms(): void
    {
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->actingAsIn($desk, $this->hotel)->post(route('rooms.bulk'), $this->payload())->assertForbidden();
    }
}
