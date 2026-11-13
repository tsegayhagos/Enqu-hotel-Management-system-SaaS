<?php

namespace Tests\Concerns;

use App\Enums\SystemRole;
use App\Models\Hotel;
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
}
