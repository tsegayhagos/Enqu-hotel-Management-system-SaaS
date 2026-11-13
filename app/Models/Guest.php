<?php

namespace App\Models;

use App\Enums\IdType;
use App\Enums\VipLevel;
use App\Models\Scopes\HotelScope;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'id_type',
        'id_number',
        'nationality',
        'car_plate_number',
        'vip_level',
        'is_blacklisted',
        'blacklist_reason',
        'total_stays_count',
        'special_preferences',
    ];

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'vip_level' => VipLevel::class,
            'is_blacklisted' => 'boolean',
            'total_stays_count' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->withoutGlobalScope(HotelScope::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class)->withoutGlobalScope(HotelScope::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}")
        );
    }

    public function isBlacklisted(): bool
    {
        return (bool) $this->is_blacklisted;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('id_number', 'like', "%{$term}%");
        });
    }

    public function scopeNotBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', false);
    }

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', true);
    }
}
