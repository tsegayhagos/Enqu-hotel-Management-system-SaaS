<?php

namespace App\Http\Controllers\FrontDesk;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();
        $hotelId = $hotel->id;
        $today = Carbon::today()->toDateString();

        $arrivals = Reservation::where('hotel_id', $hotelId)
            ->where('check_in_date', '<=', $today)
            ->where('status', ReservationStatus::Confirmed)
            ->with(['guest', 'room', 'roomType'])
            ->orderBy('check_in_date')
            ->get();

        $departures = Reservation::where('hotel_id', $hotelId)
            ->where('check_out_date', '<=', $today)
            ->where('status', ReservationStatus::CheckedIn)
            ->with(['guest', 'room', 'roomType', 'activeFolio'])
            ->orderBy('check_out_date')
            ->get();

        $inHouse = Reservation::where('hotel_id', $hotelId)
            ->where('status', ReservationStatus::CheckedIn)
            ->with(['guest', 'room', 'roomType', 'activeFolio'])
            ->orderBy('check_out_date')
            ->get();

        $cleanRooms = Room::where('hotel_id', $hotelId)
            ->where('status', RoomStatus::VacantClean)
            ->where('is_active', true)
            ->with('roomType')
            ->ordered()
            ->get();

        $roomTypes = RoomType::where('hotel_id', $hotelId)
            ->where('is_active', true)
            ->with('rates')
            ->get();

        return Inertia::render('FrontDesk/Dashboard', [
            'metrics' => [
                'expected_arrivals' => $arrivals->count(),
                'expected_departures' => $departures->count(),
                'in_house_stays' => $inHouse->count(),
                'available_clean_rooms' => $cleanRooms->count(),
                'business_date' => $today,
            ],
            'arrivals' => $arrivals,
            'departures' => $departures,
            'inHouse' => $inHouse,
            'vacantCleanRooms' => $cleanRooms,
            'roomTypes' => $roomTypes,
        ]);
    }
}
