<?php

namespace App\Models;

use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyHotelStat extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'stat_date',
        'total_rooms',
        'out_of_order_rooms',
        'available_rooms',
        'occupied_rooms',
        'occupancy_rate',
        'adr',
        'revpar',
        'room_revenue',
        'laundry_revenue',
        'restaurant_pos_revenue',
        'minibar_fee_revenue',
        'tax_collected',
        'total_gross_revenue',
        'payments_collected',
        'arrivals_count',
        'departures_count',
        'cancellations_count',
        'no_shows_count',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date:Y-m-d',
            'total_rooms' => 'integer',
            'out_of_order_rooms' => 'integer',
            'available_rooms' => 'integer',
            'occupied_rooms' => 'integer',
            'occupancy_rate' => 'decimal:2',
            'adr' => 'decimal:2',
            'revpar' => 'decimal:2',
            'room_revenue' => 'decimal:2',
            'laundry_revenue' => 'decimal:2',
            'restaurant_pos_revenue' => 'decimal:2',
            'minibar_fee_revenue' => 'decimal:2',
            'tax_collected' => 'decimal:2',
            'total_gross_revenue' => 'decimal:2',
            'payments_collected' => 'decimal:2',
            'arrivals_count' => 'integer',
            'departures_count' => 'integer',
            'cancellations_count' => 'integer',
            'no_shows_count' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
