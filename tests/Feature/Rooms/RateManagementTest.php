<?php

namespace Tests\Feature\Rooms;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class RateManagementTest extends TestCase
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
        $this->type = $this->makeRoomType($this->hotel, ['base_price' => '100.00']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'room_type_id' => $this->type->id,
            'rate_name' => 'Weekend Surcharge',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'price_per_night' => '130.00',
            'days_of_week' => [6, 5],
        ];
    }

    private function onlyRate(): RoomRate
    {
        return RoomRate::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->firstOrFail();
    }

    public function test_manager_creates_a_weekend_rate_with_sorted_weekdays(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rates.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $rate = $this->onlyRate();

        $this->assertSame([5, 6], $rate->days_of_week);
        $this->assertSame('130.00', $rate->price_per_night);
        $this->assertSame($this->hotel->id, $rate->hotel_id);
        $this->assertTrue($rate->is_active);
    }

    public function test_no_weekdays_or_all_seven_mean_every_day(): void
    {
        $as = fn (array $overrides) => $this->actingAsIn($this->manager, $this->hotel)->post(route('rates.store'), $this->payload($overrides));

        $as(['rate_name' => 'None', 'days_of_week' => null])->assertSessionHasNoErrors();
        $as(['rate_name' => 'All', 'days_of_week' => [1, 2, 3, 4, 5, 6, 7]])->assertSessionHasNoErrors();

        $rates = RoomRate::query()->withoutGlobalScopes()->where('hotel_id', $this->hotel->id)->get();

        $this->assertCount(2, $rates);
        $this->assertTrue($rates->every(fn (RoomRate $r): bool => $r->days_of_week === null));
    }

    public function test_validation_rules(): void
    {
        $as = fn (array $overrides) => $this->actingAsIn($this->manager, $this->hotel)->post(route('rates.store'), $this->payload($overrides));

        $as(['end_date' => '2026-11-30'])->assertSessionHasErrors('end_date');
        $as(['price_per_night' => '-1'])->assertSessionHasErrors('price_per_night');
        $as(['price_per_night' => '1.234'])->assertSessionHasErrors('price_per_night');
        $as(['days_of_week' => [0]])->assertSessionHasErrors('days_of_week.0');
        $as(['days_of_week' => [8]])->assertSessionHasErrors('days_of_week.0');
        $as(['days_of_week' => [5, 5]])->assertSessionHasErrors('days_of_week.0');
        $as(['rate_name' => ''])->assertSessionHasErrors('rate_name');
        $as(['start_date' => '01/12/2026'])->assertSessionHasErrors('start_date');
    }

    public function test_a_room_type_of_another_hotel_is_refused(): void
    {
        $foreignType = $this->makeRoomType($this->makeHotel());

        $this->actingAsIn($this->manager, $this->hotel)
            ->post(route('rates.store'), $this->payload(['room_type_id' => $foreignType->id]))
            ->assertSessionHasErrors('room_type_id');
    }

    public function test_manager_updates_and_deletes_a_rate(): void
    {
        $rate = $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Old', 'price_per_night' => '110.00']);

        $this->actingAsIn($this->manager, $this->hotel)
            ->put(route('rates.update', $rate), $this->payload(['rate_name' => 'New', 'price_per_night' => '175.50', 'is_active' => false]))
            ->assertSessionHasNoErrors();

        $fresh = Tenant::runAs($this->hotel->id, fn () => $rate->fresh());
        $this->assertSame('New', $fresh->rate_name);
        $this->assertSame('175.50', $fresh->price_per_night);
        $this->assertFalse($fresh->is_active);

        $this->actingAsIn($this->manager, $this->hotel)->delete(route('rates.destroy', $rate))->assertRedirect();
        $this->assertNull(Tenant::runAs($this->hotel->id, fn () => $rate->fresh()));
    }

    public function test_calendar_reflects_overrides_with_exact_prices(): void
    {
        $this->makeRate($this->hotel, $this->type, [
            'rate_name' => 'Weekend', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
            'price_per_night' => '130.00', 'days_of_week' => [5, 6],
        ]);

        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rates.index', ['room_type_id' => $this->type->id, 'month' => '2026-12']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rates/Index')
                ->where('month', '2026-12')
                ->has('calendar', 31)
                ->where('calendar.2.is_override', false)   // Thu 3 Dec
                ->where('calendar.2.price', '100.00')
                ->where('calendar.3.is_override', true)    // Fri 4 Dec
                ->where('calendar.3.price', '130.00')
                ->where('calendar.3.rate_name', 'Weekend')
                ->has('rates', 1));
    }

    public function test_index_defaults_to_the_first_room_type(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rates.index'))
            ->assertInertia(fn (Assert $page) => $page->where('selectedTypeId', $this->type->id));
    }

    public function test_index_without_room_types_renders_an_empty_state(): void
    {
        $emptyHotel = $this->makeHotel();
        $manager = $this->makeStaff($emptyHotel, SystemRole::HotelManager);

        $this->actingAsIn($manager, $emptyHotel)
            ->get(route('rates.index'))
            ->assertInertia(fn (Assert $page) => $page->where('selectedTypeId', null)->has('calendar', 0));
    }

    public function test_invalid_month_is_rejected(): void
    {
        $this->actingAsIn($this->manager, $this->hotel)
            ->get(route('rates.index', ['month' => '2026-13']))
            ->assertSessionHasErrors('month');
    }

    public function test_only_users_with_rate_manage_can_use_rates(): void
    {
        $desk = $this->makeStaff($this->hotel, SystemRole::FrontDesk);
        $rate = $this->makeRate($this->hotel, $this->type);

        $this->actingAsIn($desk, $this->hotel)->get(route('rates.index'))->assertForbidden();
        $this->actingAsIn($desk, $this->hotel)->post(route('rates.store'), $this->payload())->assertForbidden();
        $this->actingAsIn($desk, $this->hotel)->delete(route('rates.destroy', $rate))->assertForbidden();
    }

    public function test_another_hotels_rates_are_untouchable(): void
    {
        $other = $this->makeHotel();
        $foreignRate = $this->makeRate($other, $this->makeRoomType($other));

        $this->actingAsIn($this->manager, $this->hotel)->put(route('rates.update', $foreignRate->id), $this->payload())->assertNotFound();
        $this->actingAsIn($this->manager, $this->hotel)->delete(route('rates.destroy', $foreignRate->id))->assertNotFound();

        $this->assertNotNull(Tenant::runAs($other->id, fn () => $foreignRate->fresh()));
    }
}
