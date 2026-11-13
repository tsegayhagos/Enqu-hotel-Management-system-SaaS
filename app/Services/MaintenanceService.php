<?php

namespace App\Services;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Enums\RoomStatus;
use App\Models\Hotel;
use App\Models\MaintenanceTicket;
use App\Models\MaintenanceTicketPhoto;
use App\Models\Room;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    /**
     * Create a new maintenance ticket and optionally place the linked room Out of Order.
     *
     * @param  array{
     *     room_id?: ?int,
     *     location_label?: ?string,
     *     assigned_to_user_id?: ?int,
     *     title: string,
     *     description: string,
     *     category?: string|MaintenanceCategory,
     *     priority?: string|MaintenancePriority,
     *     blocks_room?: ?bool,
     *     estimated_cost?: float|int|string|null
     * }  $data
     * @param  array<int, UploadedFile>  $uploadedPhotos
     */
    public function createTicket(
        Hotel $hotel,
        array $data,
        User $reporter,
        array $uploadedPhotos = []
    ): MaintenanceTicket {
        return Tenant::runAs($hotel->id, function () use ($hotel, $data, $reporter, $uploadedPhotos) {
            $roomId = ! empty($data['room_id']) ? (int) $data['room_id'] : null;
            $room = $roomId ? Room::where('hotel_id', $hotel->id)->findOrFail($roomId) : null;

            $category = $data['category'] ?? MaintenanceCategory::Other;
            if (is_string($category)) {
                $category = MaintenanceCategory::tryFrom($category) ?? MaintenanceCategory::Other;
            }

            $priority = $data['priority'] ?? MaintenancePriority::Medium;
            if (is_string($priority)) {
                $priority = MaintenancePriority::tryFrom($priority) ?? MaintenancePriority::Medium;
            }

            $blocksRoom = array_key_exists('blocks_room', $data) && $data['blocks_room'] !== null
                ? (bool) $data['blocks_room']
                : ($room !== null && in_array($priority, [MaintenancePriority::High, MaintenancePriority::Emergency], true));

            if (! $room) {
                $blocksRoom = false;
            }

            if ($blocksRoom && $room && $room->status === RoomStatus::Occupied) {
                throw ValidationException::withMessages([
                    'blocks_room' => "Room {$room->room_number} is currently Occupied. Move or check out the guest before taking it Out of Order.",
                ]);
            }

            $assignedUserId = ! empty($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : null;
            if ($assignedUserId) {
                User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))->findOrFail($assignedUserId);
            }

            return DB::transaction(function () use (
                $hotel,
                $room,
                $reporter,
                $assignedUserId,
                $data,
                $category,
                $priority,
                $blocksRoom,
                $uploadedPhotos
            ) {
                $ticket = MaintenanceTicket::create([
                    'hotel_id' => $hotel->id,
                    'room_id' => $room?->id,
                    'location_label' => $data['location_label'] ?? ($room ? "Room {$room->room_number}" : 'Public Area'),
                    'reported_by_user_id' => $reporter->id,
                    'assigned_to_user_id' => $assignedUserId,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'category' => $category,
                    'priority' => $priority,
                    'status' => MaintenanceTicketStatus::Open,
                    'blocks_room' => $blocksRoom,
                    'estimated_cost' => $data['estimated_cost'] ?? null,
                ]);

                foreach ($uploadedPhotos as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }
                    $path = $file->store("maintenance-tickets/{$hotel->id}", 'public');
                    MaintenanceTicketPhoto::create([
                        'hotel_id' => $hotel->id,
                        'maintenance_ticket_id' => $ticket->id,
                        'photo_path' => $path,
                        'uploaded_by_user_id' => $reporter->id,
                    ]);
                }

                if ($blocksRoom && $room && $room->status !== RoomStatus::OutOfOrder) {
                    $room->setOutOfOrder();
                }

                return $ticket->fresh(['room.roomType', 'reportedBy', 'assignedTo', 'photos']);
            });
        });
    }

    /**
     * Update ticket assignment, work progress, or priority.
     *
     * @param  array{
     *     status?: string|MaintenanceTicketStatus,
     *     assigned_to_user_id?: ?int,
     *     priority?: string|MaintenancePriority,
     *     estimated_cost?: float|int|string|null,
     *     actual_cost?: float|int|string|null,
     *     resolution_notes?: ?string
     * }  $data
     */
    public function updateTicket(MaintenanceTicket $ticket, array $data): MaintenanceTicket
    {
        return Tenant::runAs($ticket->hotel_id, function () use ($ticket, $data) {
            if (array_key_exists('assigned_to_user_id', $data)) {
                $uid = ! empty($data['assigned_to_user_id']) ? (int) $data['assigned_to_user_id'] : null;
                if ($uid) {
                    User::whereHas('hotels', fn ($q) => $q->whereKey($ticket->hotel_id))->findOrFail($uid);
                }
                $ticket->assigned_to_user_id = $uid;
            }

            if (array_key_exists('priority', $data) && $data['priority'] !== null) {
                $priority = is_string($data['priority'])
                    ? MaintenancePriority::tryFrom($data['priority'])
                    : $data['priority'];
                if ($priority) {
                    $ticket->priority = $priority;
                }
            }

            if (array_key_exists('estimated_cost', $data)) {
                $ticket->estimated_cost = $data['estimated_cost'];
            }

            if (array_key_exists('actual_cost', $data)) {
                $ticket->actual_cost = $data['actual_cost'];
            }

            if (array_key_exists('resolution_notes', $data)) {
                $ticket->resolution_notes = $data['resolution_notes'];
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $targetStatus = is_string($data['status'])
                    ? MaintenanceTicketStatus::tryFrom($data['status'])
                    : $data['status'];

                if (! $targetStatus) {
                    throw ValidationException::withMessages(['status' => 'Invalid maintenance status.']);
                }

                if ($targetStatus === MaintenanceTicketStatus::Resolved) {
                    $ticket->save();

                    return $this->resolveTicket(
                        $ticket,
                        $data['resolution_notes'] ?? $ticket->resolution_notes,
                        $data['actual_cost'] ?? $ticket->actual_cost,
                        $ticket->assigned_to_user_id
                    );
                }

                if ($ticket->status !== $targetStatus) {
                    if (! $ticket->canTransitionTo($targetStatus)) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot move ticket from '{$ticket->status->label()}' to '{$targetStatus->label()}'.",
                        ]);
                    }
                    $ticket->status = $targetStatus;
                    if ($targetStatus === MaintenanceTicketStatus::Open) {
                        $ticket->resolved_at = null;
                    }
                }
            }

            $ticket->save();

            return $ticket->fresh(['room.roomType', 'reportedBy', 'assignedTo', 'photos']);
        });
    }

    /**
     * Resolve a maintenance ticket and release an Out-of-Order room back to Vacant Dirty if unblocked.
     */
    public function resolveTicket(
        MaintenanceTicket $ticket,
        ?string $resolutionNotes = null,
        float|int|string|null $actualCost = null,
        ?int $assignedToUserId = null
    ): MaintenanceTicket {
        return Tenant::runAs($ticket->hotel_id, function () use ($ticket, $resolutionNotes, $actualCost, $assignedToUserId) {
            if (
                $ticket->status !== MaintenanceTicketStatus::Resolved
                && ! $ticket->canTransitionTo(MaintenanceTicketStatus::Resolved)
            ) {
                throw ValidationException::withMessages([
                    'status' => "Ticket cannot be resolved from '{$ticket->status->label()}'.",
                ]);
            }

            return DB::transaction(function () use ($ticket, $resolutionNotes, $actualCost, $assignedToUserId) {
                if ($assignedToUserId) {
                    User::whereHas('hotels', fn ($q) => $q->whereKey($ticket->hotel_id))->findOrFail($assignedToUserId);
                    $ticket->assigned_to_user_id = $assignedToUserId;
                }

                if ($resolutionNotes !== null) {
                    $ticket->resolution_notes = $resolutionNotes;
                }

                if ($actualCost !== null) {
                    $ticket->actual_cost = $actualCost;
                }

                $ticket->status = MaintenanceTicketStatus::Resolved;
                $ticket->resolved_at ??= now();
                $ticket->save();

                if ($ticket->room_id) {
                    $room = Room::where('hotel_id', $ticket->hotel_id)->lockForUpdate()->find($ticket->room_id);

                    if ($room && $room->status === RoomStatus::OutOfOrder) {
                        $otherBlockingTicket = MaintenanceTicket::where('hotel_id', $ticket->hotel_id)
                            ->where('room_id', $room->id)
                            ->where('id', '!=', $ticket->id)
                            ->where('blocks_room', true)
                            ->whereIn('status', [
                                MaintenanceTicketStatus::Open->value,
                                MaintenanceTicketStatus::InProgress->value,
                            ])
                            ->exists();

                        if (! $otherBlockingTicket) {
                            $room->markAsDirty();
                        }
                    }
                }

                return $ticket->fresh(['room.roomType', 'reportedBy', 'assignedTo', 'photos']);
            });
        });
    }
}
