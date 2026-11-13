<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentMethod;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FolioService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ReportWebTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $accountant;

    protected User $housekeeper;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel(['slug' => 'grand-enku', 'currency' => 'USD']);
        $this->accountant = $this->makeStaff($this->hotel, SystemRole::Accountant);
        $this->housekeeper = $this->makeStaff($this->hotel, SystemRole::Housekeeping);
        $this->superAdmin = $this->makeSuperAdmin();

        Tenant::runAs($this->hotel->id, function () {
            $rt = RoomType::create([
                'hotel_id' => $this->hotel->id,
                'name' => 'Deluxe',
                'slug' => 'deluxe',
                'base_occupancy' => 2,
                'max_occupancy' => 2,
                'base_price' => '150.00',
            ]);

            Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $rt->id,
                'room_number' => '101',
                'floor_number' => 1,
                'is_active' => true,
            ]);

            $folioService = app(FolioService::class);
            $folio = $folioService->openFolio($this->hotel, ['guest_name' => 'Audit Guest']);
            $folioService->addCharge($folio, TransactionType::RoomCharge, 150.00, 'Stay');
            $folioService->recordPayment($folio, 150.00, PaymentMethod::Cash, 'REC-10', $this->accountant);
        });
    }

    public function test_authorized_roles_can_view_reports_and_unauthorized_blocked(): void
    {
        $this->actingAsIn($this->housekeeper, $this->hotel)
            ->get('/reports')
            ->assertForbidden();

        $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports')
            ->assertOk();

        $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports/cashier-handover')
            ->assertOk();

        $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports/occupancy-calendar')
            ->assertOk();
    }

    public function test_csv_export_streams_valid_csv_data_and_super_admin_sees_platform_rollup(): void
    {
        $response = $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports/export?type=cashier_handover');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csvContent = $response->streamedContent();
        $this->assertStringContainsString('Cashier Name', $csvContent);
        $this->assertStringContainsString('150.00', $csvContent);

        $deptCsv = $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports/export?type=departmental_revenue')
            ->streamedContent();
        $this->assertStringContainsString('Room Revenue', $deptCsv);

        $summaryCsv = $this->actingAsIn($this->accountant, $this->hotel)
            ->get('/reports/export?type=daily_summary')
            ->streamedContent();
        $this->assertStringContainsString('Room Revenue', $summaryCsv);

        // Super admin platform multi-hotel comparison
        $this->actingAsIn($this->superAdmin, $this->hotel)
            ->get('/reports')
            ->assertOk();
    }
}
