<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Lets multi-property staff choose / switch the hotel they are working in. */
class HotelSwitchController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/SelectHotel');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hotel_id' => ['required', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canAccessHotel((int) $data['hotel_id']), 403, 'You do not have access to this hotel.');

        session([Tenant::SESSION_KEY => (int) $data['hotel_id']]);

        return redirect()->route($user->is_super_admin ? 'admin.hotels.index' : 'dashboard');
    }
}
