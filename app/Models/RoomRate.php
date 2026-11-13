<?php

namespace App\Models;

use App\Traits\BelongsToHotel;
use Database\Factories\RoomRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRate extends Model
{
    /** @use HasFactory<RoomRateFactory> */
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'room_type_id',
        'rate_name',
        'start_date',
        'end_date',
        'price_per_night',
        'days_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'price_per_night' => 'decimal:2',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** ISO weekday: 1 = Monday ... 7 = Sunday. A null/empty list means "every day". */
    public function appliesToWeekday(int $isoWeekday): bool
    {
        return empty($this->days_of_week) || in_array($isoWeekday, $this->days_of_week, true);
    }
}
