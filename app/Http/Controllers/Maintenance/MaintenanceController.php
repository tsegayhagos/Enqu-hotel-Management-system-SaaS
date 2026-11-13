<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\ResolveMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceTicketRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTicketRequest;
use App\Models\MaintenanceTicket;
use App\Models\Room;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function __construct(
        protected MaintenanceService $maintenanceService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $hotel = $user->currentHotel();

        $tickets = MaintenanceTicket::where('hotel_id', $hotel->id)
            ->with(['room.roomType', 'reportedBy', 'assignedTo', 'photos'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE priority WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('id')
            ->get();

        $rooms = Room::where('hotel_id', $hotel->id)
            ->with('roomType')
            ->orderBy('floor_number')
            ->orderBy('room_number')
            ->get();

        $staff = User::whereHas('hotels', fn ($q) => $q->whereKey($hotel->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Maintenance/Index', [
            'tickets' => $tickets,
            'rooms' => $rooms,
            'staff' => $staff,
            'currency' => $hotel->currency ?? 'USD',
            'canManage' => $user->can('maintenance.manage'),
        ]);
    }

    public function store(StoreMaintenanceTicketRequest $request): RedirectResponse
    {
        $user = $request->user();
        $hotel = $user->currentHotel();

        $ticket = $this->maintenanceService->createTicket(
            hotel: $hotel,
            data: $request->validated(),
            reporter: $user,
            uploadedPhotos: $request->file('photos', [])
        );

        return redirect()->back()->with('success', "Maintenance ticket #{$ticket->id} ({$ticket->title}) logged successfully.");
    }

    public function update(UpdateMaintenanceTicketRequest $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($ticket->hotel_id !== $hotel->id) {
            abort(403, 'Maintenance ticket does not belong to active hotel.');
        }

        $this->maintenanceService->updateTicket($ticket, $request->validated());

        return redirect()->back()->with('success', "Maintenance ticket #{$ticket->id} updated.");
    }

    public function resolve(ResolveMaintenanceTicketRequest $request, MaintenanceTicket $ticket): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($ticket->hotel_id !== $hotel->id) {
            abort(403, 'Maintenance ticket does not belong to active hotel.');
        }

        $validated = $request->validated();

        $this->maintenanceService->resolveTicket(
            ticket: $ticket,
            resolutionNotes: $validated['resolution_notes'] ?? null,
            actualCost: $validated['actual_cost'] ?? null,
            assignedToUserId: $validated['assigned_to_user_id'] ?? null
        );

        return redirect()->back()->with('success', "Ticket #{$ticket->id} resolved.");
    }
}
