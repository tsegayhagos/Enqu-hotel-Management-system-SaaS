<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Hotel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'detectedHotel' => $this->detectHotel($request),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        // Staff attached to several hotels choose where to work first.
        if (! $user->is_super_admin && $user->hotels()->where('hotels.is_active', true)->count() > 1) {
            session()->forget(Tenant::SESSION_KEY);

            return redirect()->route('hotels.select');
        }

        if ($user->is_super_admin) {
            return redirect()->intended(route('admin.hotels.index'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Hotel hinted by the sub-domain (grand.enku.test), shown on the login page. */
    private function detectHotel(Request $request): ?array
    {
        $central = config('tenancy.central_domain');

        if (! is_string($central) || $central === '' || ! str_ends_with($request->getHost(), '.'.$central)) {
            return null;
        }

        $slug = substr($request->getHost(), 0, -strlen('.'.$central));
        $hotel = Hotel::query()->where('slug', $slug)->where('is_active', true)->first();

        return $hotel !== null ? ['name' => $hotel->name, 'slug' => $hotel->slug] : null;
    }
}
