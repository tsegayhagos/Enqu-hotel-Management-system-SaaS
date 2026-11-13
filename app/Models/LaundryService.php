<?php

namespace App\Models;

use App\Enums\LaundryCategory;
use App\Enums\LaundryServiceType;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryService extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'item_name',
        'category',
        'service_type',
        'standard_price',
        'express_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => LaundryCategory::class,
            'service_type' => LaundryServiceType::class,
            'standard_price' => 'decimal:2',
            'express_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class);
    }
}
