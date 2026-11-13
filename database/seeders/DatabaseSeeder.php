<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DemoHotelSeeder::class,
            RoomInventorySeeder::class,
            FolioBillingSeeder::class,
            GuestReservationSeeder::class,
            LaundryCatalogSeeder::class,
        ]);
    }
}
