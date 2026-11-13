<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\RoomStatus;
use App\Enums\TransactionType;
use App\Models\Hotel;
use App\Models\Room;
use App\Services\FolioService;
use App\Services\NightAuditService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FolioBillingSeeder extends Seeder
{
    public function run(FolioService $folioService, NightAuditService $nightAuditService): void
    {
        $hotel = Hotel::where('slug', 'grand-enku-resort')->first() ?? Hotel::first();

        if (! $hotel) {
            return;
        }

        $rooms = Room::where('hotel_id', $hotel->id)->orderBy('room_number')->take(3)->get();
        if ($rooms->isEmpty()) {
            return;
        }

        // Room 1: Occupied with balance
        $room1 = $rooms[0];
        $room1->status = RoomStatus::Occupied;
        $room1->save();

        $folio1 = $folioService->openFolio($hotel, [
            'room_id' => $room1->id,
            'guest_name' => 'Abebe Bikila',
            'notes' => 'VIP Guest - Early Check-in',
        ]);

        $folioService->addCharge(
            folio: $folio1,
            type: TransactionType::RoomCharge,
            amount: '120.00',
            description: 'Room Charge - Night 1',
            ref: 'RC-101-01'
        );

        $folioService->addCharge(
            folio: $folio1,
            type: TransactionType::Tax,
            amount: '18.00',
            description: 'VAT (15%)',
            ref: 'TAX-01'
        );

        $folioService->addCharge(
            folio: $folio1,
            type: TransactionType::RestaurantPos,
            amount: '45.50',
            description: 'Sky Lounge Dinner & Drinks',
            ref: 'POS-7821'
        );

        $folioService->recordPayment(
            folio: $folio1,
            amount: '100.00',
            method: PaymentMethod::CreditCard,
            ref: 'CC-AUTH-8910'
        );

        // Room 2: Fully settled
        if (isset($rooms[1])) {
            $room2 = $rooms[1];
            $room2->status = RoomStatus::Occupied;
            $room2->save();

            $folio2 = $folioService->openFolio($hotel, [
                'room_id' => $room2->id,
                'guest_name' => 'Derartu Tulu',
                'notes' => 'Corporate billing arrangement',
            ]);

            $folioService->addCharge(
                folio: $folio2,
                type: TransactionType::RoomCharge,
                amount: '200.00',
                description: 'Executive Suite - Night 1',
                ref: 'RC-201-01'
            );

            $folioService->addCharge(
                folio: $folio2,
                type: TransactionType::Tax,
                amount: '30.00',
                description: 'VAT (15%)',
                ref: 'TAX-02'
            );

            $folioService->recordPayment(
                folio: $folio2,
                amount: '230.00',
                method: PaymentMethod::BankTransfer,
                ref: 'BANK-TRANSFER-992'
            );
        }

        // Run Night Audit for yesterday to seed audit log history
        $nightAuditService->runAudit($hotel, Carbon::yesterday()->toDateString());
    }
}
