<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Every value is a closure: Inertia calls share() BEFORE route middleware
     * (IdentifyTenant) runs, so tenant-dependent data must resolve lazily at render time.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn (): array => $this->authData($request),
            'hotel' => fn (): ?array => $this->hotelData($request),
            'hotels' => fn (): array => $this->switchableHotels($request),
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function authData(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return ['user' => null, 'permissions' => [], 'roles' => []];
        }

        $hasHotel = Tenant::hotel() !== null;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
            ],
            'permissions' => ! $user->is_super_admin && $hasHotel
                ? $user->getAllPermissions()->pluck('name')->values()->all()
                : [],
            'roles' => $hasHotel ? $user->getRoleNames()->values()->all() : [],
        ];
    }

    /** @return array<string, mixed>|null */
    private function hotelData(Request $request): ?array
    {
        $hotel = $request->user() !== null ? Tenant::hotel() : null;

        if ($hotel === null) {
            return null;
        }

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'slug' => $hotel->slug,
            'currency' => $hotel->currency,
            'timezone' => $hotel->timezone,
            'check_in_time' => substr((string) $hotel->check_in_time, 0, 5),
            'check_out_time' => substr((string) $hotel->check_out_time, 0, 5),
            'tax_rate' => (float) $hotel->tax_rate,
        ];
    }

    /** @return list<array{id: int, name: string}> */
    private function switchableHotels(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $hotels = $user->is_super_admin
            ? Hotel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : $user->hotels()->where('hotels.is_active', true)->orderBy('hotels.name')->get(['hotels.id', 'hotels.name']);

        return $hotels->map(fn (Hotel $h): array => ['id' => $h->id, 'name' => $h->name])->all();
    }
}
