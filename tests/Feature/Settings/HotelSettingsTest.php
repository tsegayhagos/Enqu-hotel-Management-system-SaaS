<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HotelSettingsTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Renamed Hotel',
            'email' => 'new@hotel.test',
            'phone' => '+251900000000',
            'address' => 'New street',
            'city' => 'Hawassa',
            'country' => 'Ethiopia',
            'currency' => 'USD',
            'timezone' => 'Africa/Nairobi',
            'check_in_time' => '13:30',
            'check_out_time' => '10:00',
        ];
    }

    public function test_manager_updates_timezone_currency_and_default_times(): void
    {
        $hotel = $this->makeHotel();
        $manager = $this->makeStaff($hotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $hotel)
            ->put(route('settings.hotel.update'), $this->payload())
            ->assertSessionHasNoErrors();

        $hotel->refresh();
        $this->assertSame('USD', $hotel->currency);
        $this->assertSame('Africa/Nairobi', $hotel->timezone);
        $this->assertSame('13:30', substr((string) $hotel->check_in_time, 0, 5));
        $this->assertSame('10:00', substr((string) $hotel->check_out_time, 0, 5));
    }

    public function test_updated_settings_propagate_through_the_tenant_helper(): void
    {
        $hotel = $this->makeHotel();
        $manager = $this->makeStaff($hotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $hotel)->put(route('settings.hotel.update'), $this->payload());

        $current = Tenant::runAs($hotel->id, fn () => Tenant::hotel());

        $this->assertSame('Africa/Nairobi', $current->timezone);
    }

    public function test_settings_only_change_the_active_hotel(): void
    {
        $a = $this->makeHotel(['currency' => 'ETB']);
        $b = $this->makeHotel(['currency' => 'ETB']);
        $manager = $this->makeStaff($a, SystemRole::HotelManager);

        $this->actingAsIn($manager, $a)->put(route('settings.hotel.update'), $this->payload());

        $this->assertSame('ETB', $b->fresh()->currency);
    }

    public function test_invalid_times_are_rejected(): void
    {
        $hotel = $this->makeHotel();
        $manager = $this->makeStaff($hotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $hotel)
            ->put(route('settings.hotel.update'), $this->payload(['check_in_time' => '25:99']))
            ->assertSessionHasErrors('check_in_time');
    }
}
