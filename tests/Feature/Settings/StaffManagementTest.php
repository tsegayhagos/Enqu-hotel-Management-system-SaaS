<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StaffManagementTest extends TestCase
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

    public function test_manager_can_create_a_staff_member_with_a_role(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'Sara Desk',
                'email' => 'sara@example.test',
                'role' => 'front-desk',
                'password' => 'temp-pass-123',
            ])
            ->assertRedirect(route('settings.staff.index'));

        $sara = User::query()->where('email', 'sara@example.test')->firstOrFail();

        $this->assertSame(SystemRole::FrontDesk, app(StaffService::class)->roleOf($this->hotel, $sara));
    }

    public function test_new_accounts_require_a_temporary_password(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'No Pass',
                'email' => 'nopass@example.test',
                'role' => 'front-desk',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_existing_account_is_attached_instead_of_duplicated(): void
    {
        $other = $this->makeHotel();
        $existing = $this->makeStaff($other, SystemRole::LaundryStaff, ['email' => 'shared@example.test']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'Ignored',
                'email' => 'shared@example.test',
                'role' => 'housekeeping',
            ])
            ->assertRedirect(route('settings.staff.index'));

        $this->assertSame(1, User::query()->where('email', 'shared@example.test')->count());
        $this->assertSame(SystemRole::Housekeeping, app(StaffService::class)->roleOf($this->hotel, $existing));
        $this->assertSame(SystemRole::LaundryStaff, app(StaffService::class)->roleOf($other, $existing));
    }

    public function test_cannot_add_the_same_person_twice(): void
    {
        $this->makeStaff($this->hotel, SystemRole::FrontDesk, ['email' => 'dup@example.test']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'Dup', 'email' => 'dup@example.test', 'role' => 'front-desk', 'password' => 'temp-pass-123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_account_cannot_be_added_as_staff(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'x', 'email' => $admin->email, 'role' => 'front-desk',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_role_cannot_be_assigned_through_the_form(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('settings.staff.store'), [
                'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'role' => 'super-admin', 'password' => 'temp-pass-123',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_manager_can_change_a_role(): void
    {
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('settings.staff.update', $desk->id), ['name' => $desk->name, 'role' => 'accountant'])
            ->assertRedirect(route('settings.staff.index'));

        $this->assertSame(SystemRole::Accountant, app(StaffService::class)->roleOf($this->hotel, $desk));
    }

    public function test_manager_cannot_change_their_own_role(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('settings.staff.update', $this->manager->id), ['name' => $this->manager->name, 'role' => 'front-desk'])
            ->assertSessionHasErrors('role');
    }

    public function test_cannot_remove_yourself(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->delete(route('settings.staff.destroy', $this->manager->id))
            ->assertSessionHasErrors('staff');
    }

    public function test_last_general_manager_cannot_be_demoted_or_removed(): void
    {
        // A platform Super Admin (not a member of the hotel) acts on its only manager.
        $admin = $this->makeSuperAdmin();

        $this->actingAsIn($admin, $this->hotel)
            ->put(route('settings.staff.update', $this->manager->id), ['name' => $this->manager->name, 'role' => 'front-desk'])
            ->assertSessionHasErrors('role');

        $this->actingAsIn($admin, $this->hotel)
            ->delete(route('settings.staff.destroy', $this->manager->id))
            ->assertSessionHasErrors('role');

        $this->assertSame(SystemRole::HotelManager, app(StaffService::class)->roleOf($this->hotel, $this->manager));
    }

    public function test_a_manager_can_be_demoted_when_another_manager_exists(): void
    {
        $second = $this->makeStaff($this->hotel, SystemRole::HotelManager);

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('settings.staff.update', $second->id), ['name' => $second->name, 'role' => 'front-desk'])
            ->assertSessionHasNoErrors();

        $this->assertSame(SystemRole::FrontDesk, app(StaffService::class)->roleOf($this->hotel, $second));
    }

    public function test_removing_staff_detaches_them_from_this_hotel_only(): void
    {
        $other = $this->makeHotel();
        $worker = $this->makeStaff($this->hotel, SystemRole::FrontDesk);
        app(StaffService::class)->assign($other, $worker, SystemRole::FrontDesk);

        $this->actingAsIn($this->manager, $this->hotel)
            ->delete(route('settings.staff.destroy', $worker->id))
            ->assertRedirect(route('settings.staff.index'));

        $this->assertFalse($worker->hotels()->whereKey($this->hotel->id)->exists());
        $this->assertTrue($worker->hotels()->whereKey($other->id)->exists());
        $this->assertNotNull(User::query()->find($worker->id));
    }
}
