<?php

namespace App\Http\Controllers\Api;

use App\Enums\FolioStatus;
use App\Enums\RoomStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PosRoomChargeRequest;
use App\Models\Folio;
use App\Models\Room;
use App\Services\FolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosChargeController extends Controller
{
    public function __construct(
        protected FolioService $folioService
    ) {}

    /**
     * List all currently occupied rooms that have open folios available for POS charging.
     */
    public function activeRooms(Request $request): JsonResponse
    {
        $hotel = $request->user()->currentHotel();

        if (! $hotel) {
            return response()->json(['error' => 'No active hotel selected.'], 400);
        }

        $openFolios = Folio::where('hotel_id', $hotel->id)
            ->where('status', FolioStatus::Open)
            ->whereNotNull('room_id')
            ->with(['room.roomType'])
            ->get();

        $activeRooms = $openFolios->filter(function ($folio) {
            return $folio->room && $folio->room->status === RoomStatus::Occupied;
        })->map(function ($folio) {
            return [
                'folio_id' => $folio->id,
                'folio_number' => $folio->folio_number,
                'room_id' => $folio->room->id,
                'room_number' => $folio->room->room_number,
                'room_type' => $folio->room->roomType?->name,
                'guest_name' => $folio->guest_name ?? 'Guest',
                'current_balance' => $folio->balance,
                'currency' => $folio->currency,
            ];
        })->values();

        return response()->json([
            'hotel_id' => $hotel->id,
            'count' => $activeRooms->count(),
            'rooms' => $activeRooms,
        ]);
    }

    /**
     * Post a charge to a guest room from a POS outlet.
     */
    public function chargeToRoom(PosRoomChargeRequest $request): JsonResponse
    {
        $hotel = $request->user()->currentHotel();

        if (! $hotel) {
            return response()->json(['error' => 'No active hotel selected.'], 400);
        }

        $validated = $request->validated();

        $folio = null;
        if (! empty($validated['folio_id'])) {
            $folio = Folio::where('hotel_id', $hotel->id)
                ->where('id', $validated['folio_id'])
                ->first();
        } elseif (! empty($validated['room_id'])) {
            $folio = Folio::where('hotel_id', $hotel->id)
                ->where('room_id', $validated['room_id'])
                ->where('status', FolioStatus::Open)
                ->latest('id')
                ->first();
        }

        if (! $folio || ! $folio->isOpen()) {
            return response()->json([
                'error' => 'No active, open guest folio found for this room.',
            ], 404);
        }

        $type = $validated['service_type'] ?? TransactionType::RestaurantPos->value;

        try {
            $tx = $this->folioService->addCharge(
                folio: $folio,
                type: $type,
                amount: $validated['amount'],
                description: $validated['description'],
                ref: $validated['reference_id'] ?? null,
                user: $request->user()
            );

            $folio->refresh();

            return response()->json([
                'message' => 'Charge successfully posted to room folio.',
                'transaction' => [
                    'id' => $tx->id,
                    'type' => $tx->transaction_type->value,
                    'amount' => $tx->amount,
                    'description' => $tx->description,
                    'reference_id' => $tx->reference_id,
                    'posted_at' => $tx->posted_at->toIso8601String(),
                ],
                'folio' => [
                    'id' => $folio->id,
                    'folio_number' => $folio->folio_number,
                    'guest_name' => $folio->guest_name,
                    'total_charges' => $folio->total_charges,
                    'total_payments' => $folio->total_payments,
                    'balance' => $folio->balance,
                    'currency' => $folio->currency,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
