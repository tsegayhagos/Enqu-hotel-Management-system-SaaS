<?php

namespace Database\Seeders;

use App\Enums\IdType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\VipLevel;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GuestReservationSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::where('slug', 'grand-enku')->first();
        if (! $hotel) {
            return;
        }

        Tenant::runAs($hotel->id, function () use ($hotel) {
            $today = Carbon::today()->toDateString();
            $tomorrow = Carbon::tomorrow()->toDateString();
            $yesterday = Carbon::yesterday()->toDateString();

            // 1. Guests
            $guest1 = Guest::updateOrCreate(
                ['hotel_id' => $hotel->id, 'phone' => '+251911223344'],
                [
                    'first_name' => 'Abebe',
                    'last_name' => 'Bikila',
                    'email' => 'abebe@example.com',
                    'id_type' => IdType::Passport,
                    'id_number' => 'EP849201',
                    'nationality' => 'Ethiopian',
                    'car_plate_number' => 'ET 3-49201',
                    'vip_level' => VipLevel::Vip,
                    'is_blacklisted' => false,
                    'total_stays_count' => 5,
                    'special_preferences' => 'Top floor suite, extra feather pillows, sparkling water.',
                ]
            );

            $guest2 = Guest::updateOrCreate(
                ['hotel_id' => $hotel->id, 'phone' => '+251922334455'],
                [
                    'first_name' => 'Derartu',
                    'last_name' => 'Tulu',
                    'email' => 'derartu@example.com',
                    'id_type' => IdType::NationalId,
                    'id_number' => 'NID-948102',
                    'nationality' => 'Ethiopian',
                    'car_plate_number' => 'ET 2-83910',
                    'vip_level' => VipLevel::Gold,
                    'is_blacklisted' => false,
                    'total_stays_count' => 3,
                    'special_preferences' => 'Quiet room facing courtyard.',
                ]
            );

            $guest3 = Guest::updateOrCreate(
                ['hotel_id' => $hotel->id, 'phone' => '+251933445566'],
                [
                    'first_name' => 'Haile',
                    'last_name' => 'Gebrselassie',
                    'email' => 'haile@example.com',
                    'id_type' => IdType::Passport,
                    'id_number' => 'EP592018',
                    'nationality' => 'Ethiopian',
                    'car_plate_number' => 'ET 3-00001',
                    'vip_level' => VipLevel::Vip,
                    'is_blacklisted' => false,
                    'total_stays_count' => 8,
                    'special_preferences' => 'Gym proximity, daily laundry service.',
                ]
            );

            $guestBlacklisted = Guest::updateOrCreate(
                ['hotel_id' => $hotel->id, 'phone' => '+251944556677'],
                [
                    'first_name' => 'Bad',
                    'last_name' => 'Actor',
                    'email' => 'badactor@example.com',
                    'id_type' => IdType::DriversLicense,
                    'id_number' => 'DL-999999',
                    'nationality' => 'Ethiopian',
                    'car_plate_number' => 'ET 1-99999',
                    'vip_level' => VipLevel::Standard,
                    'is_blacklisted' => true,
                    'blacklist_reason' => 'Repeated non-payment of mini-bar charges and disturbance in Room 204.',
                    'total_stays_count' => 1,
                    'special_preferences' => null,
                ]
            );

            $roomTypes = RoomType::where('hotel_id', $hotel->id)->get();
            $deluxeType = $roomTypes->firstWhere('slug', 'deluxe-room') ?? $roomTypes->first();
            $suiteType = $roomTypes->firstWhere('slug', 'executive-suite') ?? $roomTypes->last();

            $rooms = Room::where('hotel_id', $hotel->id)->ordered()->get();
            $occupiedRoom = $rooms->firstWhere('status', RoomStatus::Occupied);
            $cleanRooms = $rooms->where('status', RoomStatus::VacantClean);

            // Link existing Abebe Bikila folio if found
            $folioAbebe = Folio::where('hotel_id', $hotel->id)
                ->where('guest_name', 'like', '%Abebe%')
                ->first();

            // 2. Reservation: Abebe Bikila (In-House / Departing Today)
            $resAbebe = Reservation::updateOrCreate(
                ['hotel_id' => $hotel->id, 'reservation_code' => 'ENK-2026-01001'],
                [
                    'guest_id' => $guest1->id,
                    'room_id' => $occupiedRoom ? $occupiedRoom->id : $rooms[0]->id,
                    'room_type_id' => $occupiedRoom ? $occupiedRoom->room_type_id : $deluxeType->id,
                    'check_in_date' => $yesterday,
                    'check_out_date' => $today,
                    'actual_check_in_at' => Carbon::yesterday()->setHour(14),
                    'adults_count' => 2,
                    'children_count' => 0,
                    'daily_rate' => '120.00',
                    'total_amount' => '120.00',
                    'advance_deposit_paid' => '0.00',
                    'keycard_number' => 'KEY-101',
                    'status' => ReservationStatus::CheckedIn,
                    'source' => ReservationSource::WalkIn,
                    'special_requests' => 'VIP Welcome Amenities.',
                ]
            );

            if ($folioAbebe) {
                $folioAbebe->update([
                    'guest_id' => $guest1->id,
                    'reservation_id' => $resAbebe->id,
                ]);
            }

            // 3. Reservation: Derartu Tulu (Expected Arrival Today)
            $arrivalRoom = $cleanRooms->first();
            Reservation::updateOrCreate(
                ['hotel_id' => $hotel->id, 'reservation_code' => 'ENK-2026-02002'],
                [
                    'guest_id' => $guest2->id,
                    'room_id' => $arrivalRoom ? $arrivalRoom->id : null,
                    'room_type_id' => $arrivalRoom ? $arrivalRoom->room_type_id : $deluxeType->id,
                    'check_in_date' => $today,
                    'check_out_date' => Carbon::today()->addDays(2)->toDateString(),
                    'actual_check_in_at' => null,
                    'adults_count' => 1,
                    'children_count' => 0,
                    'daily_rate' => '150.00',
                    'total_amount' => '300.00',
                    'advance_deposit_paid' => '50.00',
                    'keycard_number' => null,
                    'status' => ReservationStatus::Confirmed,
                    'source' => ReservationSource::Phone,
                    'special_requests' => 'Late check-in around 6 PM.',
                ]
            );

            // 4. Reservation: Haile Gebrselassie (Upcoming Advance Booking)
            Reservation::updateOrCreate(
                ['hotel_id' => $hotel->id, 'reservation_code' => 'ENK-2026-03003'],
                [
                    'guest_id' => $guest3->id,
                    'room_id' => null,
                    'room_type_id' => $suiteType->id,
                    'check_in_date' => Carbon::today()->addDays(3)->toDateString(),
                    'check_out_date' => Carbon::today()->addDays(7)->toDateString(),
                    'actual_check_in_at' => null,
                    'adults_count' => 2,
                    'children_count' => 1,
                    'daily_rate' => '250.00',
                    'total_amount' => '1000.00',
                    'advance_deposit_paid' => '250.00',
                    'keycard_number' => null,
                    'status' => ReservationStatus::Confirmed,
                    'source' => ReservationSource::Website,
                    'special_requests' => 'Executive Suite with panoramic mountain view.',
                ]
            );
        });
    }
}
