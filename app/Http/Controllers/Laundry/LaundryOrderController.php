<?php

namespace App\Http\Controllers\Laundry;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laundry\StoreLaundryOrderRequest;
use App\Http\Requests\Laundry\UpdateLaundryStatusRequest;
use App\Models\LaundryOrder;
use App\Models\LaundryService as LaundryServiceCatalog;
use App\Models\Reservation;
use App\Services\LaundryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaundryOrderController extends Controller
{
    public function __construct(
        protected LaundryService $laundryService
    ) {}

    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $orders = LaundryOrder::where('hotel_id', $hotel->id)
            ->with(['room', 'guest', 'reservation', 'items.service', 'processedBy'])
            ->latest('id')
            ->take(100)
            ->get();

        return Inertia::render('Laundry/Index', [
            'orders' => $orders,
            'currency' => $hotel->currency ?? 'USD',
        ]);
    }

    public function create(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();

        $services = LaundryServiceCatalog::where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('item_name')
            ->get();

        // Only occupied checked-in stays can be charged to room
        $occupiedStays = Reservation::where('hotel_id', $hotel->id)
            ->where('status', ReservationStatus::CheckedIn)
            ->whereNotNull('room_id')
            ->with(['room', 'guest'])
            ->get()
            ->map(fn (Reservation $r) => [
                'reservation_id' => $r->id,
                'room_id' => $r->room_id,
                'room_number' => $r->room?->room_number ?? 'N/A',
                'guest_id' => $r->guest_id,
                'guest_name' => $r->guest?->full_name ?? 'Guest',
                'reservation_code' => $r->reservation_code,
            ]);

        return Inertia::render('Laundry/Create', [
            'services' => $services,
            'occupiedStays' => $occupiedStays,
            'currency' => $hotel->currency ?? 'USD',
        ]);
    }

    public function store(StoreLaundryOrderRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        $order = $this->laundryService->createOrder($hotel, $request->validated(), $request->user());

        return redirect()
            ->route('laundry.index')
            ->with('success', "Laundry order {$order->order_number} created successfully.");
    }

    public function updateStatus(UpdateLaundryStatusRequest $request, LaundryOrder $order): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($order->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to laundry order.');
        }

        $updated = $this->laundryService->updateStatus($order, $request->validated('status'));

        return redirect()->back()->with('success', "Order {$updated->order_number} moved to {$updated->status->label()}.");
    }

    public function postCharge(Request $request, LaundryOrder $order): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($order->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to laundry order.');
        }

        $this->laundryService->postToFolio($order, $request->user());

        return redirect()->back()->with('success', "Laundry charge for {$order->order_number} posted to guest folio.");
    }

    public function printTag(Request $request, LaundryOrder $order): Response
    {
        $hotel = $request->user()->currentHotel();
        if ($order->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to laundry order.');
        }

        $order->load(['items.service', 'room', 'guest', 'reservation', 'processedBy']);

        return Inertia::render('Laundry/PrintTag', [
            'order' => $order,
            'hotel' => [
                'name' => $hotel->name,
                'phone' => $hotel->phone,
                'currency' => $hotel->currency ?? 'USD',
            ],
        ]);
    }
}
