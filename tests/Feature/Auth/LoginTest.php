<?php

namespace Tests\Feature\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\StaffService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_staff_can_login_and_land_on_dashboard(): void
    {
        $hotel = $this->makeHotel();
        $user = $this->makeStaff($hotel, SystemRole::FrontDesk, ['email' => 'desk@example.test']);

        $this->post(route('login'), ['email' => 'desk@example.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $hotel = $this->makeHotel();
        $this->makeStaff($hotel, SystemRole::FrontDesk, ['email' => 'desk@example.test']);

        $this->post(route('login'), ['email' => 'desk@example.test', 'password' => 'nope'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_login(): void
    {
        $hotel = $this->makeHotel();
        $this->makeStaff($hotel, SystemRole::FrontDesk, ['email' => 'off@example.test', 'is_active' => false]);

        $this->post(route('login'), ['email' => 'off@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_multi_hotel_staff_are_sent_to_the_hotel_chooser(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $user = $this->makeStaff($a, SystemRole::FrontDesk, ['email' => 'multi@example.test']);
        app(StaffService::class)->assign($b, $user, SystemRole::FrontDesk);

        $this->post(route('login'), ['email' => 'multi@example.test', 'password' => 'password'])
            ->assertRedirect(route('hotels.select'));
    }

    public function test_user_without_any_hotel_is_blocked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $hotel = $this->makeHotel();
        $user = $this->makeStaff($hotel, SystemRole::FrontDesk);
        $user->update(['is_active' => false]);

        $this->actingAsIn($user, $hotel)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_hotel_chooser_sets_the_active_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $user = $this->makeStaff($a, SystemRole::FrontDesk);
        app(StaffService::class)->assign($b, $user, SystemRole::FrontDesk);

        $this->actingAsIn($user, $a)
            ->post(route('hotels.switch'), ['hotel_id' => $b->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($b->id, session(Tenant::SESSION_KEY));
    }

    public function test_logout(): void
    {
        $hotel = $this->makeHotel();
        $user = $this->makeStaff($hotel, SystemRole::FrontDesk);

        $this->actingAsIn($user, $hotel)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
