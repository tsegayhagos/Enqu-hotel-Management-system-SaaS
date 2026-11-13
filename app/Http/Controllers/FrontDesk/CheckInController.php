<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\CheckInRequest;
use App\Http\Requests\FrontDesk\WalkInCheckInRequest;
use App\Models\Reservation;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;

class CheckInController extends Controller
{
    public function __construct(
        protected CheckInService $checkInService
    ) {}

    public function checkIn(CheckInRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $reservation = Reservation::where('hotel_id', $hotel->id)
            ->findOrFail($request->validated('reservation_id'));

        $managerOverride = $request->boolean('manager_override') && $request->user()->can('checkin.override');

        $this->checkInService->checkIn(
            reservation: $reservation,
            roomId: $request->validated('room_id'),
            keycardNumber: $request->validated('keycard_number'),
            depositAmount: $request->validated('deposit_amount') ?? 0,
            paymentMethod: $request->validated('payment_method'),
            managerOverrideBlacklist: $managerOverride
        );

        return redirect()->back()->with('success', "Guest {$reservation->guest->full_name} checked in successfully to Room {$reservation->room?->room_number}.");
    }

    public function walkIn(WalkInCheckInRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $validated = $request->validated();

        $managerOverride = $request->boolean('manager_override') && $request->user()->can('checkin.override');

        $guestData = [
            'id' => $validated['guest_id'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'id_type' => $validated['id_type'] ?? null,
            'id_number' => $validated['id_number'] ?? null,
            'nationality' => $validated['nationality'] ?? 'Unknown',
            'car_plate_number' => $validated['car_plate_number'] ?? null,
            'vip_level' => $validated['vip_level'] ?? 'standard',
            'special_preferences' => $validated['special_preferences'] ?? null,
        ];

        $stayData = [
            'room_type_id' => $validated['room_type_id'],
            'room_id' => $validated['room_id'],
            'check_in_date' => $validated['check_in_date'] ?? now()->toDateString(),
            'check_out_date' => $validated['check_out_date'],
            'adults_count' => $validated['adults_count'] ?? 1,
            'children_count' => $validated['children_count'] ?? 0,
            'daily_rate' => $validated['daily_rate'],
            'total_amount' => $validated['total_amount'] ?? null,
            'keycard_number' => $validated['keycard_number'] ?? null,
            'deposit_amount' => $validated['deposit_amount'] ?? 0,
            'payment_method' => $validated['payment_method'] ?? null,
            'special_requests' => $validated['special_requests'] ?? null,
        ];

        $reservation = $this->checkInService->checkInWalkIn(
            hotel: $hotel,
            guestData: $guestData,
            stayData: $stayData,
            managerOverrideBlacklist: $managerOverride
        );

        return redirect()->back()->with('success', "Walk-in check-in complete: {$reservation->guest->full_name} checked in to Room {$reservation->room?->room_number}.");
    }
}
