<?php

namespace Tests\Feature\Integration;

use App\Enums\FolioStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\IdType;
use App\Enums\LaundryCategory;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Enums\LaundryServiceType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\NightAuditStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Enums\VipLevel;
use App\Models\DailyHotelStat;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\LaundryOrder;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\FolioService;
use App\Services\HotelOnboardingService;
use App\Services\HousekeepingService;
use App\Services\LaundryService;
use App\Services\MaintenanceService;
use App\Services\NightAuditService;
use App\Services\ReservationService;
use App\Services\StaffService;
use App\Support\Tenant;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FullHotelLifecycleTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_complete_end_to_end_hotel_operations_across_all_seven_modules(): void
    {
        // ── Module 01: Multi-tenant Hotel Onboarding & Staff Roles ──
        $onboarding = app(HotelOnboardingService::class);
        $staffService = app(StaffService::class);

        $hotel = $onboarding->onboard([
            'name' => 'Grand Lifecycle Resort',
            'email' => 'info@lifecycle.test',
            'phone' => '+251 11 555 0100',
            'address' => 'Bole Medhanialem',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'tax_rate' => 15.00,
        ], [
            'name' => 'Lifecycle Manager',
            'email' => 'lifecycle-manager@enku.test',
            'password' => 'password',
        ]);

        $manager = $hotel->users()->firstOrFail();

        $frontDesk = User::factory()->create(['email' => 'fd@lifecycle.test']);
        $housekeeper = User::factory()->create(['email' => 'hk@lifecycle.test']);
        $laundryStaff = User::factory()->create(['email' => 'laundry@lifecycle.test']);
        $cashier = User::factory()->create(['email' => 'pos@lifecycle.test']);
        $accountant = User::factory()->create(['email' => 'audit@lifecycle.test']);
        $superAdmin = $this->makeSuperAdmin();

        $staffService->assign($hotel, $frontDesk, SystemRole::FrontDesk);
        $staffService->assign($hotel, $housekeeper, SystemRole::Housekeeping);
        $staffService->assign($hotel, $laundryStaff, SystemRole::LaundryStaff);
        $staffService->assign($hotel, $cashier, SystemRole::PosCashier);
        $staffService->assign($hotel, $accountant, SystemRole::Accountant);

        // ── Module 02: Room Types, Bulk Room Creation & Seasonal Rates ──
        $roomType = Tenant::runAs($hotel->id, function () use ($hotel) {
            $rt = RoomType::create([
                'hotel_id' => $hotel->id,
                'name' => 'Executive Suite',
                'slug' => 'executive-suite',
                'base_occupancy' => 2,
                'max_occupancy' => 4,
                'base_price' => '100.00',
                'extra_person_fee' => '25.00',
            ]);

            RoomRate::create([
                'hotel_id' => $hotel->id,
                'room_type_id' => $rt->id,
                'rate_name' => 'Peak Season',
                'start_date' => Carbon::today()->subDay()->toDateString(),
                'end_date' => Carbon::today()->addDays(10)->toDateString(),
                'price_per_night' => '120.00',
                'days_of_week' => [],
                'is_active' => true,
            ]);

            return $rt;
        });

        $this->actingAsIn($manager, $hotel)
            ->post(route('rooms.bulk'), [
                'room_type_id' => $roomType->id,
                'floor_number' => 1,
                'prefix' => '',
                'start_number' => 101,
                'end_number' => 103,
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();

        $rooms = Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->orderBy('room_number')->get();
        $this->assertCount(3, $rooms);
        $room101 = $rooms[0];
        $room102 = $rooms[1];

        // ── Module 03: Guest CRM, Reservation & Check-In with Deposit ──
        $guest = Tenant::runAs($hotel->id, fn () => Guest::create([
            'hotel_id' => $hotel->id,
            'first_name' => 'Selamawit',
            'last_name' => 'Tadesse',
            'email' => 'selamawit@guest.test',
            'phone' => '+251911000999',
            'id_type' => IdType::Passport,
            'id_number' => 'EP-998877',
            'nationality' => 'Ethiopian',
            'vip_level' => VipLevel::Gold,
        ]));

        $reservation = app(ReservationService::class)->createReservation($hotel, [
            'guest_id' => $guest->id,
            'room_type_id' => $roomType->id,
            'room_id' => $room101->id,
            'check_in_date' => Carbon::today()->toDateString(),
            'check_out_date' => Carbon::today()->addDays(2)->toDateString(),
            'adults_count' => 2,
            'children_count' => 0,
            'daily_rate' => '100.00',
            'source' => ReservationSource::Website->value,
        ]);

        $this->assertEquals(ReservationStatus::Confirmed, $reservation->status);

        $checkedIn = app(CheckInService::class)->checkIn(
            reservation: $reservation,
            roomId: $room101->id,
            keycardNumber: 'KC-101',
            depositAmount: '50.00',
            paymentMethod: PaymentMethod::Cash,
        );

        $this->assertEquals(ReservationStatus::CheckedIn, $checkedIn->status);
        $room101->refresh();
        $this->assertEquals(RoomStatus::Occupied, $room101->status);

        /** @var Folio $folio */
        $folio = Folio::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('reservation_id', $reservation->id)
            ->where('status', FolioStatus::Open->value)
            ->firstOrFail();

        // ── Module 04: Laundry Service Order Charged to Room Folio ──
        $laundryItem = Tenant::runAs($hotel->id, fn () => LaundryServiceCatalog::create([
            'hotel_id' => $hotel->id,
            'item_name' => 'Executive Suit',
            'category' => LaundryCategory::Apparel,
            'service_type' => LaundryServiceType::DryClean,
            'standard_price' => '10.00',
            'express_price' => '15.00',
            'is_active' => true,
        ]));

        $laundryOrder = app(LaundryService::class)->createOrder($hotel, [
            'room_id' => $room101->id,
            'order_type' => LaundryOrderType::GuestValet,
            'is_express' => true,
            'payment_method' => LaundryPaymentMethod::ChargeToRoom,
            'items' => [
                ['laundry_service_id' => $laundryItem->id, 'quantity' => 2],
            ],
        ], $laundryStaff);

        $this->assertInstanceOf(LaundryOrder::class, $laundryOrder);
        $this->assertEquals('30.00', $laundryOrder->total_amount);

        // ── Module 06A: External Restaurant POS Sanctum API Charge-to-Room ──
        Sanctum::actingAs($cashier);

        $this->withSession([Tenant::SESSION_KEY => $hotel->id])
            ->getJson('/api/pos/active-rooms')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('rooms.0.room_number', '101');

        $this->withSession([Tenant::SESSION_KEY => $hotel->id])
            ->postJson('/api/pos/charge-to-room', [
                'room_id' => $room101->id,
                'amount' => '20.00',
                'description' => 'Lobby Bistro Dinner',
                'reference_id' => 'POS-1001',
                'service_type' => 'restaurant_pos',
            ])
            ->assertCreated()
            ->assertJsonPath('transaction.description', 'Lobby Bistro Dinner');

        // ── Module 06B: Automated Night Audit + Module 07 DailyHotelStat Snapshot ──
        $todayStr = Carbon::today()->toDateString();
        $auditLog = app(NightAuditService::class)->runAudit($hotel, $todayStr, $accountant);

        $this->assertEquals(NightAuditStatus::Completed, $auditLog->status);
        $this->assertEquals(1, $auditLog->occupied_rooms_count);
        $this->assertEquals('100.00', $auditLog->total_room_revenue_posted);
        $this->assertEquals('15.00', $auditLog->total_tax_posted);

        $snapshot = DailyHotelStat::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->whereDate('stat_date', $todayStr)
            ->first();
        $this->assertNotNull($snapshot);

        // Folio totals: 30.00 (Laundry) + 20.00 (POS) + 100.00 (Room) + 15.00 (Tax) = 165.00; paid 50.00 -> 115.00 due
        $folio->refresh();
        $this->assertEquals('165.00', $folio->total_charges);
        $this->assertEquals('50.00', $folio->total_payments);
        $this->assertEquals('115.00', $folio->balance);

        // ── Module 06C & 03: Final Folio Card Settlement & Check-Out ──
        app(FolioService::class)->recordPayment(
            $folio,
            '115.00',
            PaymentMethod::CreditCard,
            'VISA-4421',
            $frontDesk,
            'Check-out card settlement'
        );

        $folio->refresh();
        $this->assertEquals('0.00', $folio->balance);

        $checkedOut = app(CheckOutService::class)->checkOut($checkedIn);
        $this->assertEquals(ReservationStatus::CheckedOut, $checkedOut->status);

        $room101->refresh();
        $this->assertEquals(RoomStatus::VacantDirty, $room101->status);

        // ── Module 05A: Auto-created Checkout Housekeeping Task -> Clean -> Inspected -> Approved ──
        $hkTask = HousekeepingTask::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('room_id', $room101->id)
            ->where('task_type', HousekeepingTaskType::CheckoutClean->value)
            ->first();

        $this->assertNotNull($hkTask);
        $this->assertEquals(HousekeepingTaskStatus::Pending, $hkTask->status);

        $hkService = app(HousekeepingService::class);
        $hkService->assignTask($hkTask, $housekeeper->id);
        $hkTask->refresh();
        $hkService->updateStatus($hkTask, HousekeepingTaskStatus::InProgress, $housekeeper);
        $room101->refresh();
        $this->assertEquals(RoomStatus::Cleaning, $room101->status);

        $hkTask->refresh();
        $hkService->updateStatus($hkTask, HousekeepingTaskStatus::Inspected, $housekeeper);
        $hkTask->refresh();
        $hkService->updateStatus($hkTask, HousekeepingTaskStatus::Completed, $manager);

        $room101->refresh();
        $this->assertEquals(RoomStatus::VacantClean, $room101->status);

        // ── Module 05B: Maintenance Blocking OOO Ticket & Analytics Exclusion ──
        $maintenanceService = app(MaintenanceService::class);
        $ticket = $maintenanceService->createTicket($hotel, [
            'room_id' => $room102->id,
            'title' => 'Burst bathroom pipe',
            'description' => 'Water leak under vanity sink requires replacement valve.',
            'category' => MaintenanceCategory::Plumbing,
            'priority' => MaintenancePriority::Emergency,
            'blocks_room' => true,
        ], $housekeeper);

        $room102->refresh();
        $this->assertEquals(RoomStatus::OutOfOrder, $room102->status);

        // ── Module 07: Executive Analytics, Cashier Handover & CSV Export ──
        $analytics = app(AnalyticsService::class);
        $kpi = $analytics->getKpiSummary($hotel, Carbon::today()->startOfDay(), Carbon::today()->endOfDay());

        $this->assertEquals(3, $kpi['total_rooms']);
        $this->assertEquals(1, $kpi['out_of_order_rooms']);
        $this->assertEquals(2, $kpi['available_rooms']);
        $this->assertEquals('165.00', $kpi['total_gross_revenue']);
        $this->assertEquals('165.00', $kpi['payments_collected']);

        $handover = $analytics->getCashierHandoverReport($hotel, Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
        $this->assertEquals('165.00', $handover['totals']['net_collected']);
        $this->assertEquals('50.00', $handover['totals']['cash']);
        $this->assertEquals('115.00', $handover['totals']['card']);

        // Resolve maintenance ticket -> room 102 moves from OutOfOrder to VacantDirty
        $resolvedTicket = $maintenanceService->resolveTicket($ticket, 'Replaced compression valve', '35.00', $manager->id);
        $this->assertEquals(MaintenanceTicketStatus::Resolved, $resolvedTicket->status);
        $room102->refresh();
        $this->assertEquals(RoomStatus::VacantDirty, $room102->status);

        // ── Web Routes & Role-Aware Unified Dashboard Integration Checks ──
        $this->actingAsIn($manager, $hotel)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('staffCount', 6)
                ->where('roomOverview.total', 3)
                ->where('roomOverview.vacant_clean', 2)
                ->where('roomOverview.vacant_dirty', 1)
                ->where('finance.today_revenue_posted', '165.00')
                ->where('finance.today_payments_collected', '165.00')
                ->where('capabilities.manage_staff', true)
            );

        $this->actingAsIn($housekeeper, $hotel)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('frontDesk', null)
                ->where('finance', null)
                ->where('capabilities.perform_housekeeping', true)
            );

        $this->actingAsIn($superAdmin, $hotel)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('capabilities.is_super_admin', true)
                ->has('platformOverview.hotels', 1)
            );

        $exportResponse = $this->actingAsIn($accountant, $hotel)
            ->get(route('reports.export', ['type' => 'daily_summary']));
        $exportResponse->assertOk();
        $this->assertStringContainsString('165.00', $exportResponse->streamedContent());
    }

    public function test_database_seeder_populates_grand_enku_resort_across_all_modules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hotel = Hotel::where('slug', 'grand-enku-resort')->first();
        $this->assertNotNull($hotel);

        $this->assertGreaterThanOrEqual(6, $hotel->users()->count());
        $this->assertGreaterThan(0, Room::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, Guest::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, LaundryServiceCatalog::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, LaundryOrder::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, HousekeepingTask::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, MaintenanceTicket::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
        $this->assertGreaterThan(0, Folio::withoutGlobalScopes()->where('hotel_id', $hotel->id)->count());
    }
}
