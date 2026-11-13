<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreStaffRequest;
use App\Http\Requests\Settings\UpdateStaffRequest;
use App\Models\Hotel;
use App\Models\User;
use App\Services\StaffService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $staff) {}

    public function index(Request $request): Response
    {
        $hotel = Tenant::requireHotel();

        $users = $hotel->users()
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user): array => $this->present($hotel, $user, $request->user()));

        return Inertia::render('Settings/Staff/Index', [
            'staff' => $users,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        Tenant::requireHotel();

        return Inertia::render('Settings/Staff/Create', ['roles' => $this->roleOptions()]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $hotel = Tenant::requireHotel();
        $data = $request->validated();
        $role = SystemRole::from($data['role']);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null) {
            if ($user->is_super_admin) {
                throw ValidationException::withMessages(['email' => 'This e-mail cannot be added as hotel staff.']);
            }

            if ($user->hotels()->whereKey($hotel->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'This person is already staff at this hotel.']);
            }
        } else {
            if (empty($data['password'])) {
                throw ValidationException::withMessages(['password' => 'A temporary password is required for a new account.']);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);
        }

        $this->staff->assign($hotel, $user, $role);

        return redirect()->route('settings.staff.index')->with('success', $user->name.' added as '.$role->label().'.');
    }

    public function edit(Request $request, int $user): Response
    {
        $hotel = Tenant::requireHotel();
        $member = $hotel->users()->findOrFail($user);

        return Inertia::render('Settings/Staff/Edit', [
            'member' => $this->present($hotel, $member, $request->user()),
            'canEditProfile' => $member->hotels()->count() === 1,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateStaffRequest $request, int $user): RedirectResponse
    {
        $hotel = Tenant::requireHotel();
        $member = $hotel->users()->findOrFail($user);
        $data = $request->validated();
        $newRole = SystemRole::from($data['role']);

        if ($member->is($request->user()) && $newRole !== $this->staff->roleOf($hotel, $member)) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        $this->guardLastManager($hotel, $member, $newRole);

        // Profile data belongs to the account, so only edit it when the person works nowhere else.
        if ($member->hotels()->count() === 1) {
            $member->update(array_intersect_key($data, array_flip(['name', 'phone'])));
        }

        $this->staff->changeRole($hotel, $member, $newRole);

        return redirect()->route('settings.staff.index')->with('success', 'Staff member updated.');
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $hotel = Tenant::requireHotel();
        $member = $hotel->users()->findOrFail($user);

        if ($member->is($request->user())) {
            throw ValidationException::withMessages(['staff' => 'You cannot remove yourself.']);
        }

        $this->guardLastManager($hotel, $member, null);

        $this->staff->remove($hotel, $member);

        return redirect()->route('settings.staff.index')->with('success', 'Staff member removed from this hotel.');
    }

    /** A hotel must always keep at least one General Manager. */
    private function guardLastManager(Hotel $hotel, User $member, ?SystemRole $newRole): void
    {
        $isManager = $this->staff->roleOf($hotel, $member) === SystemRole::HotelManager;

        if ($isManager && $newRole !== SystemRole::HotelManager && $this->staff->managerCount($hotel) <= 1) {
            throw ValidationException::withMessages(['role' => 'The hotel must keep at least one General Manager.']);
        }
    }

    /** @return array<string, mixed> */
    private function present(Hotel $hotel, User $user, ?User $viewer): array
    {
        $role = $this->staff->roleOf($hotel, $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_self' => $viewer !== null && $user->is($viewer),
            'role' => $role === null ? null : [
                'value' => $role->value,
                'label' => $role->label(),
                'variant' => $role->badgeVariant(),
            ],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        return array_map(
            fn (SystemRole $role): array => ['value' => $role->value, 'label' => $role->label()],
            SystemRole::hotelRoles(),
        );
    }
}
