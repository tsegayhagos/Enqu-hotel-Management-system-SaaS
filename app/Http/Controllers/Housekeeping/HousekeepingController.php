<?php

namespace App\Http\Controllers\Housekeeping;

use App\Enums\HousekeepingTaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Housekeeping\BulkAssignHousekeepingRequest;
use App\Http\Requests\Housekeeping\StoreHousekeepingTaskRequest;
use App\Http\Requests\Housekeeping\UpdateHousekeepingTaskStatusRequest;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function __construct(
        protected HousekeepingService $housekeepingService
    ) {}

    public function myTasks(Request $request): Response
    {
        $user = $request->user();
        $hotel = $user->currentHotel();

        $myTasks = HousekeepingTask::where('hotel_id', $hotel->id)
            ->where(function ($query) use ($user): void {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereNull('assigned_to_user_id');
            })
            ->where('status', '!=', HousekeepingTaskStatus::Completed->value)
            ->with(['room.roomType', 'assignedTo'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->get();

        $completedToday = HousekeepingTask::where('hotel_id', $hotel->id)
            ->where('assigned_to_user_id', $user->id)
            ->where('status', HousekeepingTaskStatus::Completed->value)
            ->whereDate('completed_at', today()->toDateString())
            ->with(['room.roomType'])
            ->latest('completed_at')
            ->get();

        $rooms = Room::where('hotel_id', $hotel->id)
            ->orderBy('room_number')
            ->get(['id', 'room_number', 'floor_number', 'status']);

        return Inertia::render('Housekeeping/StaffMobileView', [
            'myTasks' => $myTasks,
            'completedToday' => $completedToday,
            'rooms' => $rooms,
            'canApprove' => $user->can('housekeeping.manage'),
        ]);
    }

    public function supervisor(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('roomType')
            ->orderBy('floor_number')
            ->orderBy('room_number')
            ->get();

        $tasks = HousekeepingTask::where('hotel_id', $hotel->id)
            ->with(['room.roomType', 'assignedTo', 'inspectedBy'])
            ->orderByRaw("CASE status WHEN 'inspected' THEN 0 WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->latest('id')
            ->take(150)
            ->get();

        $staff = User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $blockingRoomIds = MaintenanceTicket::where('hotel_id', $hotel->id)
            ->whereNotNull('room_id')
            ->where('blocks_room', true)
            ->whereIn('status', ['open', 'in_progress'])
            ->pluck('room_id')
            ->unique()
            ->values();

        return Inertia::render('Housekeeping/SupervisorDashboard', [
            'rooms' => $rooms,
            'tasks' => $tasks,
            'staff' => $staff,
            'blockingRoomIds' => $blockingRoomIds,
        ]);
    }

    public function store(StoreHousekeepingTaskRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $this->housekeepingService->createTask($hotel, $request->validated());

        return redirect()->back()->with('success', 'Housekeeping task created successfully.');
    }

    public function updateStatus(UpdateHousekeepingTaskStatusRequest $request, HousekeepingTask $task): RedirectResponse
    {
        $user = $request->user();
        $hotel = $user->currentHotel();

        if ($task->hotel_id !== $hotel->id) {
            abort(403, 'Task does not belong to active hotel.');
        }

        $targetStatus = $request->validated('status');
        if ($targetStatus === HousekeepingTaskStatus::Completed->value && ! $user->can('housekeeping.manage')) {
            abort(403, 'Only supervisors can inspect and complete a room clean.');
        }

        $updated = $this->housekeepingService->updateStatus(
            task: $task,
            toStatus: $targetStatus,
            actor: $user,
            notes: $request->validated('notes')
        );

        return redirect()->back()->with('success', "Room {$updated->room?->room_number} task updated to {$updated->status->label()}.");
    }

    public function bulkAssign(BulkAssignHousekeepingRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $validated = $request->validated();

        $count = $this->housekeepingService->bulkAssign(
            hotel: $hotel,
            taskIds: $validated['task_ids'],
            userId: $validated['assigned_to_user_id'] ?? null
        );

        return redirect()->back()->with('success', "Updated assignment for {$count} housekeeping task(s).");
    }

    public function generateStayovers(Request $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $created = $this->housekeepingService->generateStayoverTasks($hotel);

        return redirect()->back()->with('success', "Generated {$created} stayover housekeeping task(s) for occupied rooms.");
    }
}
