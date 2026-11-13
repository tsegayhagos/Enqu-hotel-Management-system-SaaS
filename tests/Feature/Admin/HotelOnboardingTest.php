<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HotelOnboardingTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Skyline Lodge',
            'email' => 'hello@skyline.test',
            'phone' => '+251911000000',
            'address' => 'Piassa',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'admin_name' => 'Skyline GM',
            'admin_email' => 'gm@skyline.test',
            'admin_password' => 'secret-pass-123',
        ];
    }

    public function test_super_admin_onboards_a_hotel_with_its_general_manager(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post(route('admin.hotels.store'), $this->payload())
            ->assertRedirect(route('admin.hotels.index'));

        $hotel = Hotel::query()->where('slug', 'skyline-lodge')->firstOrFail();
        $gm = User::query()->where('email', 'gm@skyline.test')->firstOrFail();

        $this->assertTrue($gm->hotels()->whereKey($hotel->id)->exists());
        $this->assertSame(SystemRole::HotelManager, app(StaffService::class)->roleOf($hotel, $gm));
    }

    public function test_slug_collisions_get_a_numeric_suffix(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.hotels.store'), $this->payload());
        $this->actingAs($admin)->post(route('admin.hotels.store'), $this->payload(['admin_email' => 'gm2@skyline.test']));

        $this->assertDatabaseHas('hotels', ['slug' => 'skyline-lodge']);
        $this->assertDatabaseHas('hotels', ['slug' => 'skyline-lodge-2']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post(route('admin.hotels.store'), $this->payload(['timezone' => 'Mars/Olympus']))
            ->assertSessionHasErrors('timezone');
    }

    public function test_hotel_managers_cannot_onboard_hotels(): void
    {
        $hotel = $this->makeHotel();
        $manager = $this->makeStaff($hotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $hotel)
            ->post(route('admin.hotels.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_super_admin_can_suspend_a_hotel_and_its_staff_lose_access(): void
    {
        $hotel = $this->makeHotel();
        $receptionist = $this->makeStaff($hotel, SystemRole::FrontDesk);

        $this->actingAs($this->makeSuperAdmin())->patch(route('admin.hotels.update', $hotel))->assertRedirect();

        $this->assertFalse($hotel->fresh()->is_active);
        $this->actingAsIn($receptionist, $hotel)->get(route('dashboard'))->assertForbidden();
    }
}
