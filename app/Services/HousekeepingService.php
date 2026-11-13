<?php

namespace App\Services;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\RoomStatus;
use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HousekeepingService
{
    /**
     * Create a manual housekeeping task for a room.
     *
     * @param  array{
     *     room_id: int,
     *     assigned_to_user_id?: ?int,
     *     task_type?: string|HousekeepingTaskType,
     *     priority?: string|HousekeepingPriority,
     *     due_date?: ?string,
     *     notes?: ?string
     * }  $data
     */
    public function createTask(Hotel $hotel, array $data): HousekeepingTask
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $data) {
            $room = Room::where('hotel_id', $hotel->id)->findOrFail($data['room_id']);

            $assignedUserId = ! empty($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : null;
            if ($assignedUserId) {
                User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))->findOrFail($assignedUserId);
            }

            $taskType = $data['task_type'] ?? HousekeepingTaskType::CheckoutClean;
            if (is_string($taskType)) {
                $taskType = HousekeepingTaskType::tryFrom($taskType) ?? HousekeepingTaskType::CheckoutClean;
            }

            $priority = $data['priority'] ?? HousekeepingPriority::Normal;
            if (is_string($priority)) {
                $priority = HousekeepingPriority::tryFrom($priority) ?? HousekeepingPriority::Normal;
            }

            return HousekeepingTask::create([
                'hotel_id' => $hotel->id,
                'room_id' => $room->id,
                'assigned_to_user_id' => $assignedUserId,
                'task_type' => $taskType,
                'priority' => $priority,
                'status' => HousekeepingTaskStatus::Pending,
                'due_date' => $data['due_date'] ?? Carbon::today()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Assign or unassign a housekeeping task to a staff member.
     */
    public function assignTask(HousekeepingTask $task, ?int $userId): HousekeepingTask
    {
        return Tenant::runAs($task->hotel_id, function () use ($task, $userId) {
            if ($userId) {
                User::whereHas('hotels', fn ($q) => $q->whereKey($task->hotel_id))->findOrFail($userId);
            }

            $task->assigned_to_user_id = $userId;
            $task->save();

            return $task->fresh(['room.roomType', 'assignedTo', 'inspectedBy']);
        });
    }

    /**
     * Bulk assign multiple non-completed housekeeping tasks to an attendant.
     *
     * @param  array<int, int>  $taskIds
     */
    public function bulkAssign(Hotel $hotel, array $taskIds, ?int $userId): int
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $taskIds, $userId) {
            if ($userId) {
                User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))->findOrFail($userId);
            }

            return HousekeepingTask::where('hotel_id', $hotel->id)
                ->whereIn('id', $taskIds)
                ->where('status', '!=', HousekeepingTaskStatus::Completed->value)
                ->update(['assigned_to_user_id' => $userId]);
        });
    }

    /**
     * Advance task status and keep linked room status synchronized.
     */
    public function updateStatus(
        HousekeepingTask $task,
        HousekeepingTaskStatus|string $toStatus,
        ?User $actor = null,
        ?string $notes = null
    ): HousekeepingTask {
        return Tenant::runAs($task->hotel_id, function () use ($task, $toStatus, $actor, $notes) {
            $target = is_string($toStatus)
                ? HousekeepingTaskStatus::tryFrom($toStatus)
                : $toStatus;

            if (! $target) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid housekeeping task status.',
                ]);
            }

            if ($task->status === $target) {
                return $task;
            }

            if (! $task->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition task from '{$task->status->label()}' to '{$target->label()}'.",
                ]);
            }

            return DB::transaction(function () use ($task, $target, $actor, $notes) {
                $room = Room::where('hotel_id', $task->hotel_id)->lockForUpdate()->findOrFail($task->room_id);

                if ($notes !== null) {
                    $task->notes = $notes;
                }

                if ($target === HousekeepingTaskStatus::InProgress) {
                    $task->started_at ??= now();
                    if (! $task->assigned_to_user_id && $actor) {
                        $task->assigned_to_user_id = $actor->id;
                    }

                    if ($room->status === RoomStatus::VacantDirty) {
                        $room->startCleaning();
                    }
                } elseif ($target === HousekeepingTaskStatus::Completed) {
                    $task->started_at ??= now();
                    $task->completed_at = now();
                    $task->inspected_by_user_id = $actor?->id;

                    $hasBlockingIssue = MaintenanceTicket::where('hotel_id', $task->hotel_id)
                        ->where('room_id', $room->id)
                        ->where('blocks_room', true)
                        ->whereIn('status', [
                            MaintenanceTicketStatus::Open->value,
                            MaintenanceTicketStatus::InProgress->value,
                        ])
                        ->exists();

                    if (! $hasBlockingIssue && in_array($room->status, [RoomStatus::VacantDirty, RoomStatus::Cleaning], true)) {
                        $room->markAsClean();
                    }
                } elseif ($target === HousekeepingTaskStatus::Pending) {
                    // Rejected back from inspection to pending re-clean
                    $task->completed_at = null;
                    $task->inspected_by_user_id = null;

                    if (in_array($room->status, [RoomStatus::Cleaning, RoomStatus::VacantClean], true)) {
                        $room->markAsDirty();
                    }
                }

                $task->status = $target;
                $task->save();

                return $task->fresh(['room.roomType', 'assignedTo', 'inspectedBy']);
            });
        });
    }

    /**
     * Generate daily stayover clean tasks for occupied rooms not yet queued today.
     */
    public function generateStayoverTasks(Hotel $hotel, ?Carbon $date = null): int
    {
        return Tenant::runAs($hotel->id, function () use ($hotel, $date) {
            $targetDate = ($date ?? Carbon::today())->toDateString();

            $occupiedRooms = Room::where('hotel_id', $hotel->id)
                ->where('status', RoomStatus::Occupied->value)
                ->get();

            $created = 0;

            foreach ($occupiedRooms as $room) {
                $exists = HousekeepingTask::where('hotel_id', $hotel->id)
                    ->where('room_id', $room->id)
                    ->whereDate('due_date', $targetDate)
                    ->where('status', '!=', HousekeepingTaskStatus::Completed->value)
                    ->exists();

                if ($exists) {
                    continue;
                }

                HousekeepingTask::create([
                    'hotel_id' => $hotel->id,
                    'room_id' => $room->id,
                    'task_type' => HousekeepingTaskType::StayoverClean,
                    'priority' => HousekeepingPriority::Normal,
                    'status' => HousekeepingTaskStatus::Pending,
                    'due_date' => $targetDate,
                    'notes' => 'Scheduled stayover service.',
                ]);

                $created++;
            }

            return $created;
        });
    }
}
