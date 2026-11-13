<?php

namespace App\Support;

use App\Models\Hotel;
use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for "which hotel is active right now".
 *
 * HTTP requests resolve it from session('active_hotel_id') (set by the
 * IdentifyTenant middleware). Console code (seeders, night audit, queued
 * jobs) must wrap work in Tenant::runAs($hotelId, fn () => ...).
 */
final class Tenant
{
    public const SESSION_KEY = 'active_hotel_id';

    private static ?int $override = null;

    public static function id(): ?int
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $id = session(self::SESSION_KEY);

        return $id !== null ? (int) $id : null;
    }

    public static function hotel(): ?Hotel
    {
        $id = self::id();

        if ($id === null) {
            return null;
        }

        $key = 'tenant.hotel.'.$id;

        if (! app()->bound($key)) {
            app()->instance($key, Hotel::query()->find($id));
        }

        return app($key);
    }

    public static function requireHotel(): Hotel
    {
        $hotel = self::hotel();

        abort_if($hotel === null, 403, 'No active hotel selected.');

        return $hotel;
    }

    public static function forget(): void
    {
        $id = self::id();

        if ($id !== null && app()->bound('tenant.hotel.'.$id)) {
            app()->forgetInstance('tenant.hotel.'.$id);
        }
    }

    /**
     * Run a callback with a specific hotel as the active tenant (console, jobs, tests).
     *
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public static function runAs(int $hotelId, Closure $callback): mixed
    {
        $previous = self::$override;
        self::$override = $hotelId;

        $registrar = app(PermissionRegistrar::class);
        $prevTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($hotelId);

        try {
            return $callback();
        } finally {
            self::$override = $previous;
            $registrar->setPermissionsTeamId($prevTeam);
        }
    }
}
