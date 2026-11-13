<?php

namespace App\Models;

use App\Support\Tenant;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'currency',
        'timezone',
        'check_in_time',
        'check_out_time',
        'tax_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'tax_rate' => 'decimal:2',
        ];
    }

    /** The hotel resolved for the current request / console context. */
    public static function current(): ?self
    {
        return Tenant::hotel();
    }

    /** Staff attached to this hotel (multi-property pivot). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_primary')->withTimestamps();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
