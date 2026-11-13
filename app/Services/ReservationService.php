<?php

namespace App\Services;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        protected ReservationCodeGenerator $codeGenerator
    ) {}

    /**
     * Check if a room is available for the given dates.
     */
    public function isRoomAvailable(int $hotelId, int $roomId, string $checkIn, string $checkOut, ?int $ignoreReservationId = null): bool
    {
        $query = Reservation::where('hotel_id', $hotelId)
            ->where('room_id', $roomId)
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::CheckedIn])
            ->whereDate('check_in_date', '<', $checkOut)
            ->whereDate('check_out_date', '>', $checkIn);

        if ($ignoreReservationId) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        return ! $query->exists();
    }

    /**
     * Check room type inventory availability for given dates.
     */
    public function isRoomTypeAvailable(int $hotelId, int $roomTypeId, string $checkIn, string $checkOut, ?int $ignoreReservationId = null): bool
    {
        $totalRooms = Room::where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->count();

        if ($totalRooms === 0) {
            return false;
        }

        // Count peak overlapping reservations for this room type
        $query = Reservation::where('hotel_id', $hotelId)
            ->where('room_type_id', $roomTypeId)
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::CheckedIn])
            ->whereDate('check_in_date', '<', $checkOut)
            ->whereDate('check_out_date', '>', $checkIn);

        if ($ignoreReservationId) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        $activeReservations = $query->count();

        return $activeReservations < $totalRooms;
    }

    /**
     * Create a new reservation.
     *
     * @param  array{
     *     guest_id: int,
     *     room_type_id: int,
     *     room_id?: ?int,
     *     check_in_date: string,
     *     check_out_date: string,
     *     adults_count?: int,
     *     children_count?: int,
     *     daily_rate: float|string,
     *     total_amount?: float|string,
     *     advance_deposit_paid?: float|string,
     *     source?: string|ReservationSource,
     *     special_requests?: ?string
     * }  $data
     */
    public function createReservation(Hotel $hotel, array $data): Reservation
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $data) {
            $checkIn = Carbon::parse($data['check_in_date'])->toDateString();
            $checkOut = Carbon::parse($data['check_out_date'])->toDateString();

            if ($checkIn >= $checkOut) {
                throw ValidationException::withMessages([
                    'check_out_date' => 'Check-out date must be after check-in date.',
                ]);
            }

            // Verify room type exists
            $roomType = RoomType::where('hotel_id', $hotel->id)->findOrFail($data['room_type_id']);

            // If specific room requested, verify availability
            $roomId = ! empty($data['room_id']) ? (int) $data['room_id'] : null;
            if ($roomId !== null) {
                $room = Room::where('hotel_id', $hotel->id)->findOrFail($roomId);
                if ($room->room_type_id !== $roomType->id) {
                    throw ValidationException::withMessages([
                        'room_id' => 'Selected room does not belong to the selected room type.',
                    ]);
                }

                if (! $this->isRoomAvailable($hotel->id, $roomId, $checkIn, $checkOut)) {
                    throw ValidationException::withMessages([
                        'room_id' => 'The selected room is already booked for the selected dates.',
                    ]);
                }
            } else {
                if (! $this->isRoomTypeAvailable($hotel->id, $roomType->id, $checkIn, $checkOut)) {
                    throw ValidationException::withMessages([
                        'room_type_id' => 'No rooms of this type are available for the selected dates.',
                    ]);
                }
            }

            // Verify guest exists
            Guest::where('hotel_id', $hotel->id)->findOrFail($data['guest_id']);

            $nights = max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));
            $dailyRate = (float) $data['daily_rate'];
            $totalAmount = isset($data['total_amount']) ? (float) $data['total_amount'] : ($dailyRate * $nights);

            $source = $data['source'] ?? ReservationSource::WalkIn;
            if (is_string($source)) {
                $source = ReservationSource::tryFrom($source) ?? ReservationSource::WalkIn;
            }

            return DB::transaction(function () use ($hotel, $data, $checkIn, $checkOut, $roomId, $dailyRate, $totalAmount, $source) {
                $code = $this->codeGenerator->generate($hotel->id);

                return Reservation::create([
                    'hotel_id' => $hotel->id,
                    'reservation_code' => $code,
                    'guest_id' => $data['guest_id'],
                    'room_id' => $roomId,
                    'room_type_id' => $data['room_type_id'],
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'adults_count' => $data['adults_count'] ?? 1,
                    'children_count' => $data['children_count'] ?? 0,
                    'daily_rate' => number_format($dailyRate, 2, '.', ''),
                    'total_amount' => number_format($totalAmount, 2, '.', ''),
                    'advance_deposit_paid' => number_format((float) ($data['advance_deposit_paid'] ?? 0), 2, '.', ''),
                    'status' => ReservationStatus::Confirmed,
                    'source' => $source,
                    'special_requests' => $data['special_requests'] ?? null,
                ]);
            });
        });
    }
}
