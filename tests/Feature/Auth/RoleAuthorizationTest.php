<?php

namespace Tests\Feature\Auth;

use App\Enums\SystemRole;
use App\Services\RoleProvisioner;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stand-in for the real laundry routes (Module 04).
        Route::middleware(['web', 'auth', 'tenant', 'permission:laundry.manage'])
            ->get('/_test/laundry', fn () => 'laundry ok');
    }

    public function test_user_without_laundry_manage_receives_403(): void
    {
        $hotel = $this->makeHotel();
        $receptionist = $this->makeStaff($hotel, SystemRole::FrontDesk);

        $this->actingAsIn($receptionist, $hotel)->get('/_test/laundry')->assertForbidden();
    }

    public function test_laundry_staff_can_access_laundry_routes(): void
    {
        $hotel = $this->makeHotel();
        $laundry = $this->makeStaff($hotel, SystemRole::LaundryStaff);

        $this->actingAsIn($laundry, $hotel)->get('/_test/laundry')->assertOk();
    }

    public function test_roles_are_isolated_per_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();

        // Same person: laundry staff in hotel A, receptionist in hotel B.
        $user = $this->makeStaff($a, SystemRole::LaundryStaff);
        app(StaffService::class)->assign($b, $user, SystemRole::FrontDesk);

        $this->actingAsIn($user, $a)->get('/_test/laundry')->assertOk();
        $this->actingAsIn($user, $b)->get('/_test/laundry')->assertForbidden();
    }

    public function test_super_admin_passes_permission_checks(): void
    {
        $hotel = $this->makeHotel();
        $admin = $this->makeSuperAdmin();

        $this->actingAsIn($admin, $hotel)->get('/_test/laundry')->assertOk();
    }

    public function test_front_desk_cannot_manage_staff_or_hotel_settings(): void
    {
        $hotel = $this->makeHotel();
        $receptionist = $this->makeStaff($hotel, SystemRole::FrontDesk);

        $this->actingAsIn($receptionist, $hotel)->get(route('settings.staff.index'))->assertForbidden();
        $this->actingAsIn($receptionist, $hotel)->get(route('settings.hotel.edit'))->assertForbidden();
    }

    public function test_every_hotel_role_gets_its_permissions(): void
    {
        $hotel = $this->makeHotel();

        foreach (SystemRole::hotelRoles() as $role) {
            $user = $this->makeStaff($hotel, $role);

            app(RoleProvisioner::class)->inTeam($hotel->id, function () use ($user, $role): void {
                $user->unsetRelation('roles')->unsetRelation('permissions');

                foreach ($role->permissions() as $permission) {
                    $this->assertTrue($user->hasPermissionTo($permission), "{$role->value} should have {$permission}");
                }
            });
        }
    }

    public function test_only_super_admin_reaches_platform_routes(): void
    {
        $hotel = $this->makeHotel();
        $manager = $this->makeStaff($hotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $hotel)->get(route('admin.hotels.index'))->assertForbidden();
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.hotels.index'))->assertOk();
    }
}
