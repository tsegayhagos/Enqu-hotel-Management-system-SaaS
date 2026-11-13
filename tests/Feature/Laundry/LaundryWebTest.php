<?php

namespace Tests\Feature\Laundry;

use App\Enums\LaundryCategory;
use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Enums\LaundryServiceType;
use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\LaundryOrder;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class LaundryWebTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    protected Hotel $hotel;

    protected User $laundryStaff;

    protected LaundryServiceCatalog $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->laundryStaff = $this->makeStaff($this->hotel, SystemRole::LaundryStaff);

        Tenant::runAs($this->hotel->id, function () {
            $this->service = LaundryServiceCatalog::create([
                'hotel_id' => $this->hotel->id,
                'item_name' => 'Dress Shirt',
                'category' => LaundryCategory::Apparel,
                'service_type' => LaundryServiceType::WashAndIron,
                'standard_price' => '8.00',
                'express_price' => '12.00',
                'is_active' => true,
            ]);
        });
    }

    public function test_can_view_laundry_pipeline_and_catalog(): void
    {
        $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->get('/laundry')
            ->assertOk();

        $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->get('/laundry/services')
            ->assertOk();
    }

    public function test_can_create_order_advance_status_and_print_tag(): void
    {
        $response = $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->post('/laundry/orders', [
                'order_type' => LaundryOrderType::WalkIn->value,
                'is_express' => true,
                'payment_method' => LaundryPaymentMethod::Cash->value,
                'special_instructions' => 'No bleach',
                'items' => [
                    [
                        'laundry_service_id' => $this->service->id,
                        'quantity' => 3,
                    ],
                ],
            ]);

        $response->assertRedirect('/laundry');

        $order = Tenant::runAs($this->hotel->id, fn () => LaundryOrder::first());
        $this->assertNotNull($order);
        $this->assertEquals('36.00', $order->total_amount);

        // Advance status
        $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->patch("/laundry/orders/{$order->id}/status", [
                'status' => LaundryOrderStatus::InWash->value,
            ])
            ->assertRedirect();

        $this->assertEquals(LaundryOrderStatus::InWash, $order->fresh()->status);

        // Render print tag
        $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->get("/laundry/orders/{$order->id}/print-tag")
            ->assertOk();
    }

    public function test_can_create_catalog_item(): void
    {
        $this->actingAsIn($this->laundryStaff, $this->hotel)
            ->post('/laundry/services', [
                'item_name' => 'Silk Tie',
                'category' => LaundryCategory::Delicate->value,
                'service_type' => LaundryServiceType::DryClean->value,
                'standard_price' => '7.50',
                'express_price' => '11.25',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('laundry_services', [
            'hotel_id' => $this->hotel->id,
            'item_name' => 'Silk Tie',
            'standard_price' => '7.50',
        ]);
    }
}
