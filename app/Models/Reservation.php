<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Scopes\HotelScope;
use App\Traits\BelongsToHotel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_code',
        'guest_id',
        'room_id',
        'room_type_id',
        'check_in_date',
        'check_out_date',
        'actual_check_in_at',
        'actual_check_out_at',
        'adults_count',
        'children_count',
        'daily_rate',
        'total_amount',
        'advance_deposit_paid',
        'keycard_number',
        'status',
        'source',
        'special_requests',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'daily_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'advance_deposit_paid' => 'decimal:2',
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withoutGlobalScope(HotelScope::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScope(HotelScope::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withoutGlobalScope(HotelScope::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class)->withoutGlobalScope(HotelScope::class);
    }

    public function activeFolio(): HasOne
    {
        return $this->hasOne(Folio::class)
            ->withoutGlobalScope(HotelScope::class)
            ->latestOfMany();
    }

    public function canCheckIn(): bool
    {
        return $this->status === ReservationStatus::Confirmed;
    }

    public function canCheckOut(): bool
    {
        return $this->status === ReservationStatus::CheckedIn;
    }

    public function scopeArrivalsOn(Builder $query, string|CarbonInterface $date): Builder
    {
        $d = is_string($date) ? $date : $date->toDateString();

        return $query->where('check_in_date', $d)
            ->where('status', ReservationStatus::Confirmed);
    }

    public function scopeDeparturesOn(Builder $query, string|CarbonInterface $date): Builder
    {
        $d = is_string($date) ? $date : $date->toDateString();

        return $query->where('check_out_date', $d)
            ->where('status', ReservationStatus::CheckedIn);
    }

    public function scopeInHouse(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::CheckedIn);
    }
}
