<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\CreateReservationRequest;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();
        $hotelId = $hotel->id;

        $status = $request->query('status');
        $search = $request->query('search');

        $query = Reservation::where('hotel_id', $hotelId)
            ->with(['guest', 'room', 'roomType', 'activeFolio'])
            ->latest('id');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reservation_code', 'like', "%{$search}%")
                    ->orWhereHas('guest', function ($gq) use ($search) {
                        $gq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $reservations = $query->paginate(15)->withQueryString();
        $roomTypes = RoomType::where('hotel_id', $hotelId)->where('is_active', true)->get();

        return Inertia::render('FrontDesk/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => [
                'status' => $status ?? 'all',
                'search' => $search ?? '',
            ],
            'roomTypes' => $roomTypes,
        ]);
    }

    public function store(CreateReservationRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();

        $reservation = $this->reservationService->createReservation($hotel, $request->validated());

        return redirect()->back()->with('success', "Reservation {$reservation->reservation_code} created successfully.");
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        $this->authorizeHotel($reservation);

        $reservation->load(['guest', 'room.roomType', 'roomType', 'folios.transactions']);

        return Inertia::render('FrontDesk/Reservations/Show', [
            'reservation' => $reservation,
        ]);
    }

    protected function authorizeHotel(Reservation $reservation): void
    {
        $currentHotel = auth()->user()->currentHotel();
        if ($reservation->hotel_id !== $currentHotel->id) {
            abort(403, 'Unauthorized access to reservation.');
        }
    }
}
