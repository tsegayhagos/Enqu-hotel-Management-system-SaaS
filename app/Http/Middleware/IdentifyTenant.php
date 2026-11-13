<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use App\Models\User;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active hotel for the authenticated user.
 *
 * Priority: route {hotel} parameter > X-Hotel-Id header (API) > sub-domain >
 * session('active_hotel_id') > the user's primary hotel.
 * Explicit selectors the user may not access are rejected with 403.
 */
class IdentifyTenant
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort_if($request->expectsJson(), 403, 'This account has been deactivated.');

            return redirect()->route('login')->withErrors(['email' => 'This account has been deactivated.']);
        }

        $hotel = $this->resolveHotel($request, $user);

        if ($hotel === null) {
            session()->forget(Tenant::SESSION_KEY);
            $this->registrar->setPermissionsTeamId(null);

            abort_unless($user->is_super_admin, 403, 'No active hotel is assigned to your account.');

            return $next($request);
        }

        session([Tenant::SESSION_KEY => $hotel->id]);
        $this->registrar->setPermissionsTeamId($hotel->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }

    private function resolveHotel(Request $request, User $user): ?Hotel
    {
        $explicit = $this->explicitHotel($request);

        if ($explicit !== null) {
            abort_unless($user->canAccessHotel($explicit->id), 403, 'You do not have access to this hotel.');

            return $explicit;
        }

        $sessionId = session(Tenant::SESSION_KEY);

        if ($sessionId !== null && $user->canAccessHotel((int) $sessionId)) {
            $hotel = Hotel::query()->where('is_active', true)->find($sessionId);

            if ($hotel !== null) {
                return $hotel;
            }
        }

        return $user->hotels()
            ->where('hotels.is_active', true)
            ->orderByDesc('hotel_user.is_primary')
            ->orderBy('hotels.id')
            ->first();
    }

    private function explicitHotel(Request $request): ?Hotel
    {
        $routeParam = $request->route('hotel');

        if ($routeParam instanceof Hotel) {
            return $routeParam;
        }

        if (is_string($routeParam) || is_int($routeParam)) {
            return Hotel::query()->where('is_active', true)->whereKey($routeParam)->first();
        }

        $header = $request->header((string) config('tenancy.api_header', 'X-Hotel-Id'));

        if ($header !== null && is_numeric($header)) {
            return Hotel::query()->where('is_active', true)->find($header);
        }

        $central = config('tenancy.central_domain');

        if (is_string($central) && $central !== '' && str_ends_with($request->getHost(), '.'.$central)) {
            $slug = substr($request->getHost(), 0, -strlen('.'.$central));

            return Hotel::query()->where('is_active', true)->where('slug', $slug)->first();
        }

        return null;
    }
}
