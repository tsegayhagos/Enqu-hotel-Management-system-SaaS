<?php

namespace Tests\Feature\Billing;

use App\Enums\NightAuditStatus;
use App\Enums\RoomStatus;
use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioService;
use App\Services\NightAuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class NightAuditTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $manager;

    protected NightAuditService $nightAuditService;

    protected FolioService $folioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel(['tax_rate' => 15.00]);
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);

        $this->nightAuditService = app(NightAuditService::class);
        $this->folioService = app(FolioService::class);
    }

    public function test_night_audit_posts_room_charge_and_tax_for_occupied_rooms(): void
    {
        $roomType = $this->makeRoomType($this->hotel, [
            'base_price' => '100.00',
        ]);

        $room = $this->makeRoom($this->hotel, [
            'status' => RoomStatus::Occupied,
        ], $roomType);

        $folio = $this->folioService->openFolio($this->hotel, [
            'room_id' => $room->id,
            'guest_name' => 'Test Guest',
        ]);

        $auditDate = Carbon::yesterday()->toDateString();
        $log = $this->nightAuditService->runAudit($this->hotel, $auditDate, $this->manager);

        $this->assertEquals(NightAuditStatus::Completed, $log->status);
        $this->assertEquals(1, $log->occupied_rooms_count);
        $this->assertEquals('100.00', $log->total_room_revenue_posted);
        $this->assertEquals('15.00', $log->total_tax_posted);

        $folio->refresh();
        $this->assertEquals('115.00', $folio->total_charges);
        $this->assertEquals('115.00', $folio->balance);

        $transactions = $folio->transactions;
        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions[0]->is_immutable);
        $this->assertTrue($transactions[1]->is_immutable);
    }

    public function test_night_audit_is_strictly_idempotent(): void
    {
        $roomType = $this->makeRoomType($this->hotel, [
            'base_price' => '100.00',
        ]);

        $room = $this->makeRoom($this->hotel, [
            'status' => RoomStatus::Occupied,
        ], $roomType);

        $folio = $this->folioService->openFolio($this->hotel, [
            'room_id' => $room->id,
            'guest_name' => 'Idempotent Guest',
        ]);

        $auditDate = Carbon::yesterday()->toDateString();

        // Run 1
        $this->nightAuditService->runAudit($this->hotel, $auditDate, $this->manager);
        $folio->refresh();
        $this->assertEquals('115.00', $folio->total_charges);

        // Run 2 on same date
        $this->nightAuditService->runAudit($this->hotel, $auditDate, $this->manager);
        $folio->refresh();

        // Must still equal 115.00, not 230.00!
        $this->assertEquals('115.00', $folio->total_charges);
        $this->assertCount(2, $folio->transactions);
    }

    public function test_artisan_console_command_runs_successfully(): void
    {
        $roomType = $this->makeRoomType($this->hotel, [
            'base_price' => '120.00',
        ]);

        $room = $this->makeRoom($this->hotel, [
            'status' => RoomStatus::Occupied,
        ], $roomType);

        $this->folioService->openFolio($this->hotel, ['room_id' => $room->id]);

        $date = Carbon::yesterday()->toDateString();

        $this->artisan('hotel:night-audit', [
            '--hotel' => $this->hotel->id,
            '--date' => $date,
        ])
            ->expectsOutputToContain('Starting Night Audit')
            ->expectsOutputToContain('completed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('night_audit_logs', [
            'hotel_id' => $this->hotel->id,
            'audit_date' => $date,
            'status' => NightAuditStatus::Completed->value,
        ]);
    }
}
