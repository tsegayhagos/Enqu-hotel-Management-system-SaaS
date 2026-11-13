<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SystemRole;
use App\Models\Scopes\HotelScope;
use App\Support\Tenant;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_probes', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('hotel_id')->index();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function test_queries_are_scoped_to_the_active_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();

        Tenant::runAs($a->id, fn () => TenantProbe::create(['label' => 'A-1']));
        Tenant::runAs($b->id, fn () => TenantProbe::create(['label' => 'B-1']));

        $seenByA = Tenant::runAs($a->id, fn () => TenantProbe::pluck('label')->all());
        $seenByB = Tenant::runAs($b->id, fn () => TenantProbe::pluck('label')->all());

        $this->assertSame(['A-1'], $seenByA);
        $this->assertSame(['B-1'], $seenByB);
    }

    public function test_hotel_id_is_set_automatically_on_creation(): void
    {
        $hotel = $this->makeHotel();

        $probe = Tenant::runAs($hotel->id, fn () => TenantProbe::create(['label' => 'auto']));

        $this->assertSame($hotel->id, $probe->hotel_id);
    }

    public function test_queries_fail_closed_without_an_active_hotel(): void
    {
        $hotel = $this->makeHotel();
        Tenant::runAs($hotel->id, fn () => TenantProbe::create(['label' => 'hidden']));

        $this->assertSame(0, TenantProbe::count());
        $this->assertSame(1, TenantProbe::withoutGlobalScope(HotelScope::class)->count());
    }

    public function test_creating_without_an_active_hotel_is_refused(): void
    {
        $this->expectException(LogicException::class);

        TenantProbe::create(['label' => 'orphan']);
    }

    public function test_cross_tenant_writes_are_blocked(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();

        $this->expectException(LogicException::class);

        Tenant::runAs($a->id, fn () => TenantProbe::create(['label' => 'x', 'hotel_id' => $b->id]));
    }

    public function test_receptionist_cannot_switch_into_another_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $receptionist = $this->makeStaff($a, SystemRole::FrontDesk);

        $this->actingAsIn($receptionist, $a)
            ->post(route('hotels.switch'), ['hotel_id' => $b->id])
            ->assertForbidden();
    }

    public function test_receptionist_cannot_reach_another_hotel_through_the_api_header(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $receptionist = $this->makeStaff($a, SystemRole::FrontDesk);

        $this->actingAs($receptionist, 'web')
            ->withHeader(config('tenancy.api_header'), (string) $b->id)
            ->getJson('/api/user')
            ->assertForbidden();
    }

    public function test_session_pointing_at_a_foreign_hotel_falls_back_to_own_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $receptionist = $this->makeStaff($a, SystemRole::FrontDesk);

        $this->actingAs($receptionist)
            ->withSession([Tenant::SESSION_KEY => $b->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($a->id, session(Tenant::SESSION_KEY));
    }

    public function test_staff_of_one_hotel_are_never_listed_in_another(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $managerA = $this->makeStaff($a, SystemRole::HotelManager);
        $this->makeStaff($b, SystemRole::FrontDesk, ['name' => 'Hotel B Receptionist']);

        $this->actingAsIn($managerA, $a)
            ->get(route('settings.staff.index'))
            ->assertOk()
            ->assertDontSee('Hotel B Receptionist');
    }

    public function test_manager_cannot_edit_or_remove_staff_of_another_hotel(): void
    {
        $a = $this->makeHotel();
        $b = $this->makeHotel();
        $managerA = $this->makeStaff($a, SystemRole::HotelManager);
        $foreign = $this->makeStaff($b, SystemRole::FrontDesk);

        $this->actingAsIn($managerA, $a)
            ->get(route('settings.staff.edit', $foreign->id))
            ->assertNotFound();

        $this->actingAsIn($managerA, $a)
            ->delete(route('settings.staff.destroy', $foreign->id))
            ->assertNotFound();
    }
}

class TenantProbe extends Model
{
    use BelongsToHotel;

    protected $table = 'tenant_probes';

    protected $guarded = [];
}
