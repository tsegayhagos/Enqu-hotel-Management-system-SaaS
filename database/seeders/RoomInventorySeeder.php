<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\BulkRoomCreator;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/** Demo inventory for "Grand Enku Resort": 3 room types, 26 rooms, a weekend and a peak-season rate. */
class RoomInventorySeeder extends Seeder
{
    public function run(BulkRoomCreator $bulk): void
    {
        $hotel = Hotel::query()->where('slug', 'grand-enku-resort')->first();

        if ($hotel === null) {
            return;
        }

        Tenant::runAs($hotel->id, function () use ($bulk): void {
            if (RoomType::query()->exists()) {
                return;
            }

            $standard = RoomType::create([
                'name' => 'Standard Room', 'slug' => 'standard-room', 'description' => 'Comfortable room with city view.',
                'base_occupancy' => 2, 'max_occupancy' => 3, 'base_price' => '2500.00', 'extra_person_fee' => '500.00',
                'amenities' => ['Wi-Fi', 'TV', 'Air conditioning'],
            ]);
            $deluxe = RoomType::create([
                'name' => 'Deluxe Room', 'slug' => 'deluxe-room', 'description' => 'Spacious room with balcony.',
                'base_occupancy' => 2, 'max_occupancy' => 4, 'base_price' => '4000.00', 'extra_person_fee' => '700.00',
                'amenities' => ['Wi-Fi', 'TV', 'Mini Bar', 'Balcony'],
            ]);
            $suite = RoomType::create([
                'name' => 'Executive Suite', 'slug' => 'executive-suite', 'description' => 'Suite with living area and jacuzzi.',
                'base_occupancy' => 2, 'max_occupancy' => 4, 'base_price' => '7500.00', 'extra_person_fee' => '1000.00',
                'amenities' => ['Wi-Fi', 'TV', 'Mini Bar', 'Jacuzzi', 'Sea View'],
            ]);

            $bulk->create($standard, 1, 101, 110);
            $bulk->create($deluxe, 2, 201, 210);
            $bulk->create($suite, 3, 301, 306);

            RoomRate::create([
                'room_type_id' => $standard->id, 'rate_name' => 'Weekend Surcharge',
                'start_date' => '2026-01-01', 'end_date' => '2027-12-31',
                'price_per_night' => '3000.00', 'days_of_week' => [5, 6],
            ]);
            RoomRate::create([
                'room_type_id' => $deluxe->id, 'rate_name' => 'Peak Season (Christmas / Timkat)',
                'start_date' => '2026-12-20', 'end_date' => '2027-01-20',
                'price_per_night' => '5500.00',
            ]);
        });
    }
}
