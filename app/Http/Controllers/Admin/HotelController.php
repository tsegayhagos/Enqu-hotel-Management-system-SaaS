<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHotelRequest;
use App\Models\Hotel;
use App\Services\HotelOnboardingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** SaaS Super Admin: register and manage hotel tenants. */
class HotelController extends Controller
{
    public function __construct(private readonly HotelOnboardingService $onboarding) {}

    public function index(): Response
    {
        $hotels = Hotel::query()
            ->withCount('users')
            ->latest()
            ->paginate(15)
            ->through(fn (Hotel $hotel): array => [
                'id' => $hotel->id,
                'name' => $hotel->name,
                'slug' => $hotel->slug,
                'city' => $hotel->city,
                'country' => $hotel->country,
                'currency' => $hotel->currency,
                'tax_rate' => (float) $hotel->tax_rate,
                'is_active' => $hotel->is_active,
                'users_count' => $hotel->users_count,
                'created_at' => $hotel->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Hotels/Index', ['hotels' => $hotels]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Hotels/Create');
    }

    public function store(StoreHotelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->onboarding->onboard(
            collect($data)->except(['admin_name', 'admin_email', 'admin_password'])->all(),
            [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ],
        );

        return redirect()->route('admin.hotels.index')->with('success', 'Hotel registered with its General Manager account.');
    }

    /** Suspend or re-activate a tenant (subscription control). */
    public function update(Hotel $hotel): RedirectResponse
    {
        $hotel->update(['is_active' => ! $hotel->is_active]);

        return back()->with('success', $hotel->is_active ? 'Hotel activated.' : 'Hotel suspended.');
    }
}
