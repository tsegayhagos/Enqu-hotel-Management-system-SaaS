<?php

namespace Tests\Concerns;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoleProvisioner;
use App\Services\StaffService;
use App\Support\Tenant;

trait CreatesTenants
{
    protected function makeHotel(array $attributes = []): Hotel
    {
        $hotel = Hotel::factory()->create($attributes);
        app(RoleProvisioner::class)->provisionFor($hotel);

        return $hotel;
    }

    protected function makeStaff(Hotel $hotel, SystemRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(StaffService::class)->assign($hotel, $user, $role);

        return $user;
    }

    protected function makeSuperAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** Acts as the user inside the given hotel (session carries the active hotel). */
    protected function actingAsIn(User $user, Hotel $hotel): static
    {
        return $this->actingAs($user)->withSession([Tenant::SESSION_KEY => $hotel->id]);
    }

    // ---- Module 02 fixtures: always created inside the hotel's tenant context ----------------

    protected function makeRoomType(Hotel $hotel, array $attributes = []): RoomType
    {
        return Tenant::runAs($hotel->id, fn (): RoomType => RoomType::factory()->create($attributes));
    }

    protected function makeRoom(Hotel $hotel, array $attributes = [], ?RoomType $type = null): Room
    {
        return Tenant::runAs($hotel->id, function () use ($attributes, $type): Room {
            $type ??= RoomType::factory()->create();

            return Room::factory()->create(['room_type_id' => $type->id] + $attributes);
        });
    }

    protected function makeRate(Hotel $hotel, RoomType $type, array $attributes = []): RoomRate
    {
        return Tenant::runAs($hotel->id, fn (): RoomRate => RoomRate::factory()->create(['room_type_id' => $type->id] + $attributes));
    }
}
