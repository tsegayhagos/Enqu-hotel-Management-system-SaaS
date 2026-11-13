<?php

namespace App\Services;

use App\Enums\MaintenanceTicketStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MaintenanceTicket;
use App\Models\Reservation;
use App\Models\Room;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        protected FolioService $folioService,
        protected ReservationService $reservationService
    ) {}

    /**
     * Check in an existing confirmed reservation.
     */
    public function checkIn(
        Reservation $reservation,
        ?int $roomId = null,
        ?string $keycardNumber = null,
        float|int|string $depositAmount = 0,
        PaymentMethod|string|null $paymentMethod = null,
        bool $managerOverrideBlacklist = false
    ): Reservation {
        $hotel = $reservation->hotel ?? Hotel::findOrFail($reservation->hotel_id);

        return Tenant::runAs($hotel->id, function () use (
            $hotel,
            $reservation,
            $roomId,
            $keycardNumber,
            $depositAmount,
            $paymentMethod,
            $managerOverrideBlacklist
        ) {
            // Guard: Status must be Confirmed
            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'status' => "Cannot check in reservation with status '{$reservation->status->label()}'.",
                ]);
            }

            // Resolve Room
            $finalRoomId = $roomId ?? $reservation->room_id;
            if (! $finalRoomId) {
                throw ValidationException::withMessages([
                    'room_id' => 'A room must be selected for check-in.',
                ]);
            }

            $room = Room::where('hotel_id', $hotel->id)->findOrFail($finalRoomId);

            // Guard: Room must be Vacant Clean
            if ($room->status !== RoomStatus::VacantClean) {
                throw ValidationException::withMessages([
                    'room_id' => "Room {$room->room_number} cannot be assigned: status is {$room->status->label()} (must be Vacant Clean).",
                ]);
            }

            // Guard: No active blocking maintenance ticket on room
            $blockingTicketExists = MaintenanceTicket::where('hotel_id', $hotel->id)
                ->where('room_id', $room->id)
                ->where('blocks_room', true)
                ->whereIn('status', [
                    MaintenanceTicketStatus::Open->value,
                    MaintenanceTicketStatus::InProgress->value,
                ])
                ->exists();

            if ($blockingTicketExists) {
                throw ValidationException::withMessages([
                    'room_id' => "Room {$room->room_number} has an active blocking maintenance ticket and cannot be checked into.",
                ]);
            }

            // Guard: Guest Blacklist
            $guest = $reservation->guest ?? Guest::where('hotel_id', $hotel->id)->findOrFail($reservation->guest_id);
            if ($guest->is_blacklisted && ! $managerOverrideBlacklist) {
                $reason = $guest->blacklist_reason ? ": {$guest->blacklist_reason}" : '';
                throw ValidationException::withMessages([
                    'guest_id' => "Guest {$guest->full_name} is blacklisted{$reason}. Manager override required.",
                ]);
            }

            return DB::transaction(function () use (
                $hotel,
                $reservation,
                $room,
                $guest,
                $keycardNumber,
                $depositAmount,
                $paymentMethod
            ) {
                // 1. Move room to Occupied
                $room->setOccupied();

                // 2. Open Folio
                $folio = $this->folioService->openFolio($hotel, [
                    'reservation_id' => $reservation->id,
                    'guest_id' => $guest->id,
                    'room_id' => $room->id,
                    'guest_name' => $guest->full_name,
                    'notes' => $reservation->special_requests,
                ]);

                // 3. Deposit if provided
                $deposit = (float) $depositAmount;
                if ($deposit > 0) {
                    $method = is_string($paymentMethod)
                        ? (PaymentMethod::tryFrom($paymentMethod) ?? PaymentMethod::Cash)
                        : ($paymentMethod ?? PaymentMethod::Cash);

                    $this->folioService->recordPayment(
                        folio: $folio,
                        amount: $deposit,
                        method: $method,
                        ref: 'DEP-'.$reservation->reservation_code,
                        description: 'Advance deposit on check-in'
                    );

                    $reservation->advance_deposit_paid = number_format(
                        (float) $reservation->advance_deposit_paid + $deposit,
                        2,
                        '.',
                        ''
                    );
                }

                // 4. Update reservation status
                $reservation->room_id = $room->id;
                $reservation->keycard_number = $keycardNumber;
                $reservation->status = ReservationStatus::CheckedIn;
                $reservation->actual_check_in_at = now();
                $reservation->save();

                return $reservation->fresh(['guest', 'room', 'roomType', 'activeFolio']);
            });
        });
    }

    /**
     * Fast walk-in check-in: creates reservation and checks in immediately.
     */
    public function checkInWalkIn(
        Hotel $hotel,
        array $guestData,
        array $stayData,
        bool $managerOverrideBlacklist = false
    ): Reservation {
        return Tenant::runAs($hotel->id, function () use ($hotel, $guestData, $stayData, $managerOverrideBlacklist) {
            // Find or create guest
            $guestId = $guestData['id'] ?? null;
            if ($guestId) {
                $guest = Guest::where('hotel_id', $hotel->id)->findOrFail($guestId);
            } else {
                $guest = Guest::create([
                    'hotel_id' => $hotel->id,
                    'first_name' => $guestData['first_name'],
                    'last_name' => $guestData['last_name'],
                    'email' => $guestData['email'] ?? null,
                    'phone' => $guestData['phone'],
                    'id_type' => $guestData['id_type'],
                    'id_number' => $guestData['id_number'],
                    'nationality' => $guestData['nationality'] ?? 'Unknown',
                    'car_plate_number' => $guestData['car_plate_number'] ?? null,
                    'vip_level' => $guestData['vip_level'] ?? 'standard',
                    'special_preferences' => $guestData['special_preferences'] ?? null,
                ]);
            }

            // Create reservation
            $checkIn = Carbon::parse($stayData['check_in_date'] ?? now()->toDateString())->toDateString();
            $checkOut = Carbon::parse($stayData['check_out_date'])->toDateString();

            $reservation = $this->reservationService->createReservation($hotel, [
                'guest_id' => $guest->id,
                'room_type_id' => $stayData['room_type_id'],
                'room_id' => $stayData['room_id'],
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults_count' => $stayData['adults_count'] ?? 1,
                'children_count' => $stayData['children_count'] ?? 0,
                'daily_rate' => $stayData['daily_rate'],
                'total_amount' => $stayData['total_amount'] ?? null,
                'source' => ReservationSource::WalkIn,
                'special_requests' => $stayData['special_requests'] ?? null,
            ]);

            // Execute check-in
            return $this->checkIn(
                $reservation,
                $stayData['room_id'],
                $stayData['keycard_number'] ?? null,
                $stayData['deposit_amount'] ?? 0,
                $stayData['payment_method'] ?? null,
                $managerOverrideBlacklist
            );
        });
    }
}
