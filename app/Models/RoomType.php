<?php

namespace App\Models;

use App\Traits\BelongsToHotel;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_occupancy',
        'max_occupancy',
        'base_price',
        'extra_person_fee',
        'amenities',
    ];

    protected function casts(): array
    {
        return [
            'base_occupancy' => 'integer',
            'max_occupancy' => 'integer',
            'base_price' => 'decimal:2',
            'extra_person_fee' => 'decimal:2',
            'amenities' => 'array',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }
}
