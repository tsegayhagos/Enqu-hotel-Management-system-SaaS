<?php

namespace Database\Seeders;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\RoomStatus;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class HousekeepingMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = Hotel::all();

        foreach ($hotels as $hotel) {
            $rooms = Room::where('hotel_id', $hotel->id)->get();
            if ($rooms->isEmpty()) {
                continue;
            }

            $staff = User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))->get();
            $reporter = $staff->first();
            if (! $reporter) {
                continue;
            }

            // Create tasks for dirty or cleaning rooms if none exist
            foreach ($rooms->whereIn('status', [RoomStatus::VacantDirty, RoomStatus::Cleaning])->take(4) as $index => $room) {
                $exists = HousekeepingTask::where('hotel_id', $hotel->id)
                    ->where('room_id', $room->id)
                    ->where('status', '!=', HousekeepingTaskStatus::Completed->value)
                    ->exists();

                if ($exists) {
                    continue;
                }

                HousekeepingTask::create([
                    'hotel_id' => $hotel->id,
                    'room_id' => $room->id,
                    'assigned_to_user_id' => $staff->count() > 1 ? $staff[$index % $staff->count()]->id : $reporter->id,
                    'task_type' => HousekeepingTaskType::CheckoutClean,
                    'priority' => $index === 0 ? HousekeepingPriority::Urgent : HousekeepingPriority::Normal,
                    'status' => $room->status === RoomStatus::Cleaning
                        ? HousekeepingTaskStatus::InProgress
                        : HousekeepingTaskStatus::Pending,
                    'due_date' => today()->toDateString(),
                    'started_at' => $room->status === RoomStatus::Cleaning ? now()->subMinutes(20) : null,
                    'notes' => 'Seeded turnover task.',
                ]);
            }

            // Seed one resolved and one non-blocking open maintenance ticket
            if (! MaintenanceTicket::where('hotel_id', $hotel->id)->exists()) {
                $sampleRoom = $rooms->first();

                MaintenanceTicket::create([
                    'hotel_id' => $hotel->id,
                    'room_id' => $sampleRoom->id,
                    'location_label' => "Room {$sampleRoom->room_number}",
                    'reported_by_user_id' => $reporter->id,
                    'title' => 'AC filter service & calibration',
                    'description' => 'Routine cooling efficiency inspection requested by floor supervisor.',
                    'category' => MaintenanceCategory::AcHvac,
                    'priority' => MaintenancePriority::Medium,
                    'status' => MaintenanceTicketStatus::Open,
                    'blocks_room' => false,
                    'estimated_cost' => 45.00,
                ]);

                MaintenanceTicket::create([
                    'hotel_id' => $hotel->id,
                    'room_id' => null,
                    'location_label' => 'Main Lobby Reception',
                    'reported_by_user_id' => $reporter->id,
                    'assigned_to_user_id' => $reporter->id,
                    'title' => 'Replaced recessed LED downlights',
                    'description' => 'Replaced 2 flickering warm-white ceiling LEDs near check-in desk.',
                    'category' => MaintenanceCategory::Electrical,
                    'priority' => MaintenancePriority::Low,
                    'status' => MaintenanceTicketStatus::Resolved,
                    'blocks_room' => false,
                    'estimated_cost' => 30.00,
                    'actual_cost' => 28.50,
                    'resolution_notes' => 'Installed 2 new warm 12W LED bulbs and tested dimmer.',
                    'resolved_at' => now()->subDay(),
                ]);
            }
        }
    }
}
