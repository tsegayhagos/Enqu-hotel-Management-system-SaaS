<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Hotel;
use App\Models\User;
use App\Services\HotelOnboardingService;
use App\Services\StaffService;
use Illuminate\Database\Seeder;

/** Demo data: "Grand Enku Resort" with one staff member per role. Dev only (password: "password"). */
class DemoHotelSeeder extends Seeder
{
    public function run(HotelOnboardingService $onboarding, StaffService $staff): void
    {
        $hotel = Hotel::query()->where('slug', 'grand-enku-resort')->first()
            ?? $onboarding->onboard([
                'name' => 'Grand Enku Resort',
                'email' => 'info@grandenku.test',
                'phone' => '+251 11 000 0000',
                'address' => 'Bole Road',
                'city' => 'Addis Ababa',
                'country' => 'Ethiopia',
                'currency' => 'ETB',
                'timezone' => 'Africa/Addis_Ababa',
                'check_in_time' => '14:00',
                'check_out_time' => '11:00',
                'tax_rate' => 15.00,
            ], [
                'name' => 'Hotel Manager',
                'email' => 'manager@enku.test',
                'password' => 'password',
            ]);

        $demoStaff = [
            ['Front Desk Agent', 'frontdesk@enku.test', SystemRole::FrontDesk],
            ['Laundry Attendant', 'laundry@enku.test', SystemRole::LaundryStaff],
            ['Housekeeping Attendant', 'housekeeping@enku.test', SystemRole::Housekeeping],
            ['Restaurant Cashier', 'cashier@enku.test', SystemRole::PosCashier],
            ['Night Auditor', 'accountant@enku.test', SystemRole::Accountant],
        ];

        foreach ($demoStaff as [$name, $email, $role]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password'],
            );

            $staff->assign($hotel, $user, $role);
        }

        // Platform Super Admin (not attached to any hotel).
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@enku.test'],
            ['name' => 'Platform Admin', 'password' => 'password'],
        );
        $admin->forceFill(['is_super_admin' => true])->save();
    }
}
