<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\Hotel;
use Closure;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permission catalogue (global) and the six hotel roles
 * (one set per hotel, team key = hotel_id) with their granular permissions.
 */
class RoleProvisioner
{
    public const GUARD = 'web';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function ensurePermissions(): void
    {
        foreach (SystemRole::allPermissions() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        $this->registrar->forgetCachedPermissions();
    }

    /** The platform-level role (no team). Assigned via users.is_super_admin. */
    public function ensureSuperAdminRole(): void
    {
        $this->inTeam(null, function (): void {
            Role::findOrCreate(SystemRole::SuperAdmin->value, self::GUARD);
        });
    }

    public function provisionFor(Hotel $hotel): void
    {
        $this->ensurePermissions();

        $this->inTeam($hotel->id, function (): void {
            foreach (SystemRole::hotelRoles() as $systemRole) {
                $role = Role::findOrCreate($systemRole->value, self::GUARD);
                $role->syncPermissions($systemRole->permissions());
            }
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Run a callback with the Spatie team context set to a hotel, restoring it afterwards.
     *
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public function inTeam(?int $hotelId, Closure $callback): mixed
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($hotelId);

        try {
            return $callback();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }
}
