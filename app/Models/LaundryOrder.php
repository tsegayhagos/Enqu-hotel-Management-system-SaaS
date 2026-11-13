<?php

namespace App\Models;

use App\Enums\LaundryOrderStatus;
use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use App\Models\Scopes\HotelScope;
use App\Traits\BelongsToHotel;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryOrder extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'order_number',
        'reservation_id',
        'guest_id',
        'room_id',
        'order_type',
        'is_express',
        'status',
        'payment_method',
        'total_amount',
        'special_instructions',
        'received_at',
        'delivered_at',
        'processed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => LaundryOrderType::class,
            'is_express' => 'boolean',
            'status' => LaundryOrderStatus::class,
            'payment_method' => LaundryPaymentMethod::class,
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withoutGlobalScope(HotelScope::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withoutGlobalScope(HotelScope::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScope(HotelScope::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === LaundryOrderStatus::Delivered;
    }

    public function canTransitionTo(LaundryOrderStatus $to): bool
    {
        return $this->status->canTransitionTo($to);
    }

    /**
     * Advance to next pipeline status or throw DomainException.
     */
    public function advanceStatus(): self
    {
        $next = $this->status->nextStatus();
        if (! $next) {
            throw new DomainException("Laundry order {$this->order_number} is in terminal status {$this->status->label()}.");
        }

        $this->status = $next;
        if ($next === LaundryOrderStatus::Delivered) {
            $this->delivered_at = now();
        }
        $this->save();

        return $this;
    }
}
