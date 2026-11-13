<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\DailyHotelStat;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CheckInService;
use App\Services\FolioService;
use App\Services\NightAuditService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $manager;

    protected User $cashier;

    protected RoomType $roomType;

    protected AnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel(['currency' => 'USD']);
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
        $this->cashier = $this->makeStaff($this->hotel, SystemRole::FrontDesk);
        $this->analytics = app(AnalyticsService::class);

        Tenant::runAs($this->hotel->id, function () {
            $this->roomType = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Standard Room',
                'slug' => 'standard-room',
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'base_price' => '100.00',
            ]);
        });
    }

    public function test_occupancy_adr_and_revpar_exclude_out_of_order_rooms_and_snapshot_on_night_audit(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $rooms = [];
            for ($i = 1; $i <= 10; $i++) {
                $rooms[$i] = Room::create([
                    'hotel_id' => $this->hotel->id,
                    'room_type_id' => $this->roomType->id,
                    'room_number' => (string) (100 + $i),
                    'floor_number' => 1,
                    'status' => $i <= 2 ? RoomStatus::OutOfOrder : RoomStatus::VacantClean,
                    'is_active' => true,
                ]);
            }

            $checkInService = app(CheckInService::class);
            $folioService = app(FolioService::class);

            // Check in 4 rooms (Rooms 3..6) -> 4 occupied out of 8 sellable (10 - 2 OOO) = 50.00%
            for ($i = 3; $i <= 6; $i++) {
                $guest = Guest::create([
                    'hotel_id' => $this->hotel->id,
                    'first_name' => "Guest{$i}",
                    'last_name' => 'Test',
                    'phone' => "+2519110000{$i}",
                    'id_type' => 'passport',
                    'id_number' => "PASS{$i}",
                ]);

                $res = Reservation::create([
                    'hotel_id' => $this->hotel->id,
                    'reservation_code' => "ENK-2026-7000{$i}",
                    'guest_id' => $guest->id,
                    'room_type_id' => $this->roomType->id,
                    'room_id' => $rooms[$i]->id,
                    'check_in_date' => now()->toDateString(),
                    'check_out_date' => now()->addDays(2)->toDateString(),
                    'adults_count' => 1,
                    'daily_rate' => '100.00',
                    'total_amount' => '200.00',
                    'status' => ReservationStatus::Confirmed,
                    'source' => ReservationSource::WalkIn,
                ]);

                $checkedIn = $checkInService->checkIn($res, $rooms[$i]->id);
                $folio = $checkedIn->activeFolio;

                // Post $100 room charge each -> total $400 across 4 occupied -> ADR = 100.00, RevPAR = 50.00
                $folioService->addCharge($folio, TransactionType::RoomCharge, 100.00, 'Room night');
            }
        });

        $today = Carbon::today();
        $kpi = $this->analytics->getKpiSummary($this->hotel, $today, $today);

        $this->assertEquals(10, $kpi['total_rooms']);
        $this->assertEquals(2, $kpi['out_of_order_rooms']);
        $this->assertEquals(8, $kpi['available_rooms']);
        $this->assertEquals(50.00, $kpi['occupancy_rate']);
        $this->assertEquals('100.00', $kpi['adr']);
        $this->assertEquals('50.00', $kpi['revpar']);
        $this->assertEquals('400.00', $kpi['room_revenue']);

        // Running Night Audit automatically snapshots DailyHotelStat for that date
        app(NightAuditService::class)->runAudit($this->hotel, $today->toDateString(), $this->manager);

        $stat = DailyHotelStat::withoutGlobalScopes()
            ->where('hotel_id', $this->hotel->id)
            ->whereDate('stat_date', $today->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(8, $stat->available_rooms);
        $this->assertEquals(4, $stat->occupied_rooms);
        $this->assertEquals('50.00', $stat->occupancy_rate);
    }

    public function test_cashier_handover_reconciles_payments_and_refunds_accurately(): void
    {
        Tenant::runAs($this->hotel->id, function () {
            $folioService = app(FolioService::class);
            $folio = $folioService->openFolio($this->hotel, ['guest_name' => 'Reconcile Guest']);

            $folioService->addCharge($folio, TransactionType::RoomCharge, 500.00, 'Suite stay');
            $folioService->recordPayment($folio, 125.50, PaymentMethod::Cash, 'REC-1', $this->cashier);
            $folioService->recordPayment($folio, 200.25, PaymentMethod::CreditCard, 'REC-2', $this->cashier);
            $folioService->recordPayment($folio, 100.00, PaymentMethod::MobileMoney, 'REC-3', $this->manager);
            $folioService->recordRefund($folio, 25.50, PaymentMethod::Cash, 'Overcharge refund', 'REF-1', $this->cashier);
        });

        $report = $this->analytics->getCashierHandoverReport(
            $this->hotel,
            Carbon::today(),
            Carbon::today()
        );

        $this->assertEquals('125.50', $report['totals']['cash']);
        $this->assertEquals('200.25', $report['totals']['card']);
        $this->assertEquals('100.00', $report['totals']['mobile_money']);
        $this->assertEquals('25.50', $report['totals']['refunds']);
        $this->assertEquals('400.25', $report['totals']['net_collected']);
        $this->assertEquals(4, $report['totals']['transaction_count']);
    }

    public function test_analytics_queries_ten_thousand_transactions_under_300ms(): void
    {
        $folioId = Tenant::runAs($this->hotel->id, function () {
            Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'room_number' => '999',
                'floor_number' => 9,
                'status' => RoomStatus::Occupied,
                'is_active' => true,
            ]);

            $folio = Folio::create([
                'hotel_id' => $this->hotel->id,
                'folio_number' => 'FOL-PERF-001',
                'guest_name' => 'Perf Load',
                'currency' => 'USD',
            ]);

            return $folio->id;
        });

        $now = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < 10000; $i++) {
            $rows[] = [
                'hotel_id' => $this->hotel->id,
                'folio_id' => $folioId,
                'user_id' => $this->cashier->id,
                'transaction_type' => $i % 5 === 0 ? TransactionType::Payment->value : TransactionType::RoomCharge->value,
                'description' => 'Load tx',
                'amount' => $i % 5 === 0 ? '-50.00' : '100.00',
                'payment_method' => $i % 5 === 0 ? PaymentMethod::Cash->value : PaymentMethod::None->value,
                'is_night_audit_post' => false,
                'is_immutable' => false,
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                DB::table('folio_transactions')->insert($rows);
                $rows = [];
            }
        }

        $startTime = microtime(true);

        $kpi = $this->analytics->getKpiSummary($this->hotel, Carbon::today()->subDays(29), Carbon::today());
        $trend = $this->analytics->getDailyRevenueTrend($this->hotel, Carbon::today()->subDays(29), Carbon::today());
        $handover = $this->analytics->getCashierHandoverReport($this->hotel, Carbon::today(), Carbon::today());

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertEquals('800000.00', $kpi['room_revenue']);
        $this->assertCount(30, $trend);
        $this->assertEquals('100000.00', $handover['totals']['cash']);
        $this->assertLessThan(300, $elapsedMs, "Analytics 10k query took {$elapsedMs}ms (limit 300ms).");
    }
}
