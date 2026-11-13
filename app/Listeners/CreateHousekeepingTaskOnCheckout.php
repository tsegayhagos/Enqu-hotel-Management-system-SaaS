<?php

namespace App\Listeners;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedOutEvent;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Scopes\HotelScope;
use App\Support\Tenant;
use Carbon\Carbon;

class CreateHousekeepingTaskOnCheckout
{
    public function handle(GuestCheckedOutEvent $event): void
    {
        $reservation = $event->reservation;

        if (! $reservation->room_id || ! $reservation->hotel_id) {
            return;
        }

        Tenant::runAs($reservation->hotel_id, function () use ($reservation) {
            $alreadyExists = HousekeepingTask::where('hotel_id', $reservation->hotel_id)
                ->where('reservation_id', $reservation->id)
                ->where('task_type', HousekeepingTaskType::CheckoutClean->value)
                ->exists();

            if ($alreadyExists) {
                return;
            }

            $today = Carbon::today()->toDateString();

            $hasBackToBackArrival = Reservation::withoutGlobalScope(HotelScope::class)
                ->where('hotel_id', $reservation->hotel_id)
                ->where('id', '!=', $reservation->id)
                ->where('room_id', $reservation->room_id)
                ->whereDate('check_in_date', $today)
                ->where('status', ReservationStatus::Confirmed->value)
                ->exists();

            HousekeepingTask::create([
                'hotel_id' => $reservation->hotel_id,
                'room_id' => $reservation->room_id,
                'reservation_id' => $reservation->id,
                'task_type' => HousekeepingTaskType::CheckoutClean,
                'priority' => $hasBackToBackArrival
                    ? HousekeepingPriority::Urgent
                    : HousekeepingPriority::Normal,
                'status' => HousekeepingTaskStatus::Pending,
                'due_date' => $today,
                'notes' => $hasBackToBackArrival
                    ? 'Urgent back-to-back checkout clean (same-day arrival scheduled).'
                    : 'Auto-created on guest checkout.',
            ]);
        });
    }
}
