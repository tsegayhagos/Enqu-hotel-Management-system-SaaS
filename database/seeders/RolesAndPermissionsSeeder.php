<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Services\RoleProvisioner;
use Illuminate\Database\Seeder;

/**
 * Seeds the permission catalogue, the platform super-admin role and the six
 * hotel roles for every existing hotel. Safe to re-run (idempotent).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(RoleProvisioner $provisioner): void
    {
        $provisioner->ensurePermissions();
        $provisioner->ensureSuperAdminRole();

        Hotel::query()->each(fn (Hotel $hotel) => $provisioner->provisionFor($hotel));
    }
}
