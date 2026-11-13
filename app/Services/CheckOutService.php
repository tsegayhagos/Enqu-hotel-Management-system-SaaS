<?php

namespace App\Services;

use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedOutEvent;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutService
{
    public function __construct(
        protected FolioService $folioService
    ) {}

    /**
     * Check out a guest reservation, settling folio and releasing room to dirty status.
     */
    public function checkOut(
        Reservation $reservation,
        bool $managerOverrideBalance = false,
        ?User $authorizingUser = null
    ): Reservation {
        $hotel = $reservation->hotel ?? Hotel::findOrFail($reservation->hotel_id);

        return Tenant::runAs($hotel->id, function () use ($hotel, $reservation, $managerOverrideBalance, $authorizingUser) {
            // Guard: Reservation must be CheckedIn
            if ($reservation->status !== ReservationStatus::CheckedIn) {
                throw ValidationException::withMessages([
                    'status' => "Cannot check out reservation with status '{$reservation->status->label()}'.",
                ]);
            }

            // Find active folio for this reservation
            $folio = Folio::where('hotel_id', $hotel->id)
                ->where('reservation_id', $reservation->id)
                ->whereIn('status', [FolioStatus::Open, FolioStatus::Settled])
                ->first();

            // Guard: Folio Balance
            if ($folio && (float) $folio->balance > 0 && ! $managerOverrideBalance) {
                $balanceStr = number_format((float) $folio->balance, 2);
                throw ValidationException::withMessages([
                    'folio' => "Folio #{$folio->folio_number} has an outstanding balance of {$balanceStr} {$folio->currency}. Settle folio before checkout.",
                ]);
            }

            return DB::transaction(function () use ($reservation, $folio, $authorizingUser) {
                // 1. Close folio if open or settled
                if ($folio && in_array($folio->status, [FolioStatus::Open, FolioStatus::Settled], true)) {
                    $this->folioService->closeFolio($folio, $authorizingUser);
                }

                // 2. Mark Room as Vacant Dirty
                if ($reservation->room) {
                    $reservation->room->markAsDirty();
                }

                // 3. Increment guest stay count
                if ($reservation->guest) {
                    $reservation->guest->increment('total_stays_count');
                }

                // 4. Update reservation status
                $reservation->status = ReservationStatus::CheckedOut;
                $reservation->actual_check_out_at = now();
                $reservation->save();

                // 5. Dispatch Event (notifies Housekeeping queue in Module 05)
                GuestCheckedOutEvent::dispatch($reservation);

                return $reservation->fresh(['guest', 'room', 'roomType', 'folios']);
            });
        });
    }
}
