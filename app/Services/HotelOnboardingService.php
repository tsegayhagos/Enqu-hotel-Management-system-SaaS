<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Registers a new hotel tenant together with its roles and first General Manager. */
class HotelOnboardingService
{
    public function __construct(
        private readonly RoleProvisioner $provisioner,
        private readonly StaffService $staff,
    ) {}

    /**
     * @param  array<string, mixed>  $hotelData
     * @param  array{name: string, email: string, password: string}  $adminData
     */
    public function onboard(array $hotelData, array $adminData): Hotel
    {
        return DB::transaction(function () use ($hotelData, $adminData): Hotel {
            $hotel = Hotel::create($hotelData + ['slug' => $this->uniqueSlug((string) $hotelData['name'])]);

            $this->provisioner->provisionFor($hotel);

            $admin = User::query()->where('email', $adminData['email'])->first()
                ?? User::create([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => $adminData['password'],
                ]);

            $this->staff->assign($hotel, $admin, SystemRole::HotelManager);

            return $hotel;
        });
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hotel';
        $slug = $base;
        $counter = 2;

        while (Hotel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
