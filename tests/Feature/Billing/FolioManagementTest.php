<?php

namespace Tests\Feature\Billing;

use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FolioManagementTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $manager;

    protected FolioService $folioService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->manager = $this->makeStaff($this->hotel, SystemRole::HotelManager);
        $this->folioService = app(FolioService::class);
    }

    public function test_can_view_folios_index_page(): void
    {
        $this->folioService->openFolio($this->hotel, ['guest_name' => 'John Guest']);

        $response = $this->actingAsIn($this->manager, $this->hotel)
            ->get('/folios');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Index')
                ->has('folios.data', 1)
                ->where('folios.data.0.guest_name', 'John Guest')
            );
    }

    public function test_can_view_folio_detail_page(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, ['guest_name' => 'Alice VIP']);

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '100.00',
            description: 'Room Charge'
        );

        $response = $this->actingAsIn($this->manager, $this->hotel)
            ->get("/folios/{$folio->id}");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/FolioDetail')
                ->where('folio.id', $folio->id)
                ->where('folio.guest_name', 'Alice VIP')
                ->has('summary')
            );
    }

    public function test_can_post_charge_via_web_form(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, ['guest_name' => 'Charge Guest']);

        $response = $this->actingAsIn($this->manager, $this->hotel)
            ->post("/folios/{$folio->id}/charges", [
                'transaction_type' => 'minibar',
                'amount' => '15.00',
                'description' => 'Minibar snacks',
                'reference_id' => 'MB-101',
            ]);

        $response->assertRedirect();
        $folio->refresh();
        $this->assertEquals('15.00', $folio->balance);
        $this->assertEquals('15.00', $folio->total_charges);
    }

    public function test_can_record_payment_via_web_form(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, ['guest_name' => 'Payment Guest']);

        $this->folioService->addCharge($folio, TransactionType::RoomCharge, '100.00', 'Stay');

        $response = $this->actingAsIn($this->manager, $this->hotel)
            ->post("/folios/{$folio->id}/payments", [
                'payment_method' => 'cash',
                'amount' => '100.00',
                'reference_id' => 'REC-999',
            ]);

        $response->assertRedirect();
        $folio->refresh();
        $this->assertEquals('0.00', $folio->balance);
        $this->assertEquals('100.00', $folio->total_payments);
    }

    public function test_can_render_invoice_view_and_download_pdf(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, ['guest_name' => 'Invoice Guest']);

        // Web view
        $response = $this->actingAsIn($this->manager, $this->hotel)
            ->get("/folios/{$folio->id}/invoice");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/InvoicePrint')
                ->where('folio.id', $folio->id)
            );

        // PDF download
        $pdfResponse = $this->actingAsIn($this->manager, $this->hotel)
            ->get("/folios/{$folio->id}/invoice?format=pdf");

        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
