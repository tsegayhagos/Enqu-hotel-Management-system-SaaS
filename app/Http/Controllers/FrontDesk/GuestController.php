<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\FrontDesk\GuestRequest;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request): Response
    {
        $hotel = $request->user()->currentHotel();
        $hotelId = $hotel->id;

        $search = $request->query('search');
        $vip = $request->query('vip');
        $blacklisted = $request->query('blacklisted');

        $query = Guest::where('hotel_id', $hotelId)
            ->withCount('reservations')
            ->latest('id');

        if ($search) {
            $query->search($search);
        }

        if ($vip && $vip !== 'all') {
            $query->where('vip_level', $vip);
        }

        if ($blacklisted === 'true') {
            $query->where('is_blacklisted', true);
        } elseif ($blacklisted === 'false') {
            $query->where('is_blacklisted', false);
        }

        $guests = $query->paginate(15)->withQueryString();

        return Inertia::render('Guests/Index', [
            'guests' => $guests,
            'filters' => [
                'search' => $search ?? '',
                'vip' => $vip ?? 'all',
                'blacklisted' => $blacklisted ?? 'all',
            ],
        ]);
    }

    public function store(GuestRequest $request): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();

        $guest = Guest::create(array_merge($request->validated(), [
            'hotel_id' => $hotel->id,
        ]));

        return redirect()->back()->with('success', "Guest profile for {$guest->full_name} created successfully.");
    }

    public function update(GuestRequest $request, Guest $guest): RedirectResponse
    {
        $hotel = $request->user()->currentHotel();
        if ($guest->hotel_id !== $hotel->id) {
            abort(403, 'Unauthorized access to guest profile.');
        }

        $guest->update($request->validated());

        return redirect()->back()->with('success', "Guest profile for {$guest->full_name} updated successfully.");
    }

    public function search(Request $request): JsonResponse
    {
        $hotel = $request->user()->currentHotel();
        $query = (string) $request->query('query', '');

        if (trim($query) === '') {
            return response()->json([]);
        }

        $guests = Guest::where('hotel_id', $hotel->id)
            ->search($query)
            ->take(10)
            ->get()
            ->map(fn (Guest $g) => [
                'id' => $g->id,
                'name' => $g->full_name,
                'first_name' => $g->first_name,
                'last_name' => $g->last_name,
                'phone' => $g->phone,
                'email' => $g->email,
                'id_type' => $g->id_type->value ?? $g->id_type,
                'id_number' => $g->id_number,
                'nationality' => $g->nationality,
                'car_plate_number' => $g->car_plate_number,
                'vip_level' => $g->vip_level->value ?? $g->vip_level,
                'is_blacklisted' => (bool) $g->is_blacklisted,
                'blacklist_reason' => $g->blacklist_reason,
                'special_preferences' => $g->special_preferences,
                'total_stays_count' => $g->total_stays_count,
            ]);

        return response()->json($guests);
    }
}
