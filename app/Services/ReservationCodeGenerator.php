<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservationCodeGenerator
{
    /**
     * Generate a unique reservation code in format ENK-YYYY-XXXXX.
     */
    public function generate(int $hotelId): string
    {
        $year = now()->format('Y');

        return DB::transaction(function () use ($hotelId, $year) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $randomNumber = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
                $code = "ENK-{$year}-{$randomNumber}";

                $exists = Reservation::where('hotel_id', $hotelId)
                    ->where('reservation_code', $code)
                    ->exists();

                if (! $exists) {
                    return $code;
                }
            }

            // Fallback with microtime suffix
            return "ENK-{$year}-".substr((string) hrtime(true), -5);
        });
    }
}
