<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;

/** Attaching staff to hotels and managing their per-hotel role. */
class StaffService
{
    public function __construct(private readonly RoleProvisioner $provisioner) {}

    public function assign(Hotel $hotel, User $user, SystemRole $role): void
    {
        if (! $user->hotels()->whereKey($hotel->id)->exists()) {
            $isFirstHotel = ! $user->hotels()->exists();
            $user->hotels()->attach($hotel->id, ['is_primary' => $isFirstHotel]);
        }

        $this->provisioner->inTeam($hotel->id, function () use ($user, $role): void {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([$role->value]);
        });
    }

    public function changeRole(Hotel $hotel, User $user, SystemRole $role): void
    {
        $this->assign($hotel, $user, $role);
    }

    /** Detach the user from this hotel only (the account itself is kept). */
    public function remove(Hotel $hotel, User $user): void
    {
        $this->provisioner->inTeam($hotel->id, function () use ($user): void {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([]);
        });

        $user->hotels()->detach($hotel->id);

        // Keep a primary hotel if the user still belongs to others.
        if ($user->hotels()->exists() && ! $user->hotels()->wherePivot('is_primary', true)->exists()) {
            $user->hotels()->updateExistingPivot($user->hotels()->first()->id, ['is_primary' => true]);
        }
    }

    public function roleOf(Hotel $hotel, User $user): ?SystemRole
    {
        return $this->provisioner->inTeam($hotel->id, function () use ($user): ?SystemRole {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $name = $user->getRoleNames()->first();

            return $name !== null ? SystemRole::tryFrom($name) : null;
        });
    }

    public function managerCount(Hotel $hotel): int
    {
        return $hotel->users()
            ->get()
            ->filter(fn (User $user): bool => $this->roleOf($hotel, $user) === SystemRole::HotelManager)
            ->count();
    }
}
