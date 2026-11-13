<?php

namespace Tests\Feature\Billing;

use App\Enums\FolioStatus;
use App\Enums\PaymentMethod;
use App\Enums\SystemRole;
use App\Enums\TransactionType;
use App\Models\FolioTransaction;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class FolioServiceTest extends TestCase
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

    public function test_can_open_folio(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, [
            'guest_name' => 'John Doe',
        ]);

        $this->assertDatabaseHas('folios', [
            'id' => $folio->id,
            'hotel_id' => $this->hotel->id,
            'guest_name' => 'John Doe',
            'status' => FolioStatus::Open->value,
            'total_charges' => '0.00',
            'total_payments' => '0.00',
            'balance' => '0.00',
        ]);
        $this->assertStringStartsWith('FOL-', $folio->folio_number);
    }

    public function test_posting_charge_updates_totals_and_balance_exactly(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '150.50',
            description: 'Room Charge Night 1'
        );

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::Tax,
            amount: '22.58',
            description: 'VAT 15%'
        );

        $folio->refresh();

        $this->assertEquals('173.08', $folio->total_charges);
        $this->assertEquals('0.00', $folio->total_payments);
        $this->assertEquals('173.08', $folio->balance);
        $this->assertEquals(FolioStatus::Open, $folio->status);
    }

    public function test_recording_payment_updates_balance_and_transitions_status_to_settled(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '200.00',
            description: 'Room Charge'
        );

        $this->folioService->recordPayment(
            folio: $folio,
            amount: '200.00',
            method: PaymentMethod::CreditCard,
            ref: 'AUTH-123'
        );

        $folio->refresh();

        $this->assertEquals('200.00', $folio->total_charges);
        $this->assertEquals('200.00', $folio->total_payments);
        $this->assertEquals('0.00', $folio->balance);
        $this->assertEquals(FolioStatus::Settled, $folio->status);
    }

    public function test_cannot_post_charge_with_zero_or_negative_amount(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $this->expectException(InvalidArgumentException::class);
        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '0.00',
            description: 'Invalid'
        );
    }

    public function test_cannot_modify_or_delete_immutable_transaction(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $tx = FolioTransaction::create([
            'hotel_id' => $this->hotel->id,
            'folio_id' => $folio->id,
            'transaction_type' => TransactionType::RoomCharge,
            'description' => 'Audited charge',
            'amount' => '100.00',
            'payment_method' => PaymentMethod::None,
            'is_night_audit_post' => true,
            'is_immutable' => true,
            'posted_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $tx->description = 'Tampered description';
        $tx->save();
    }

    public function test_cannot_delete_immutable_transaction(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $tx = FolioTransaction::create([
            'hotel_id' => $this->hotel->id,
            'folio_id' => $folio->id,
            'transaction_type' => TransactionType::RoomCharge,
            'description' => 'Audited charge',
            'amount' => '100.00',
            'payment_method' => PaymentMethod::None,
            'is_night_audit_post' => true,
            'is_immutable' => true,
            'posted_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $tx->delete();
    }

    public function test_cannot_close_folio_with_outstanding_balance_without_manager_authorization(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '100.00',
            description: 'Room Charge'
        );

        $frontDeskUser = $this->makeStaff($this->hotel, SystemRole::FrontDesk);

        $this->expectException(DomainException::class);
        $this->folioService->closeFolio($folio, $frontDeskUser);
    }

    public function test_manager_can_close_folio_with_balance(): void
    {
        $folio = $this->folioService->openFolio($this->hotel);

        $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '100.00',
            description: 'Room Charge'
        );

        $closedFolio = $this->folioService->closeFolio($folio, $this->manager);

        $this->assertEquals(FolioStatus::Closed, $closedFolio->status);
        $this->assertNotNull($closedFolio->closed_at);
    }

    public function test_split_folio_moves_transactions_and_recalculates_both_balances(): void
    {
        $folio = $this->folioService->openFolio($this->hotel, ['guest_name' => 'Original Guest']);

        $tx1 = $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RoomCharge,
            amount: '150.00',
            description: 'Room Charge'
        );

        $tx2 = $this->folioService->addCharge(
            folio: $folio,
            type: TransactionType::RestaurantPos,
            amount: '50.00',
            description: 'Restaurant Dinner'
        );

        $newFolio = $this->folioService->splitFolio(
            originalFolio: $folio,
            transactionIds: [$tx2->id],
            newFolioGuestName: 'Company Master Account'
        );

        $folio->refresh();
        $newFolio->refresh();

        $this->assertEquals('150.00', $folio->total_charges);
        $this->assertEquals('150.00', $folio->balance);

        $this->assertEquals('50.00', $newFolio->total_charges);
        $this->assertEquals('50.00', $newFolio->balance);
        $this->assertEquals('Company Master Account', $newFolio->guest_name);
    }
}
