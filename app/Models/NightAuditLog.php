<?php

namespace App\Models;

use App\Enums\NightAuditStatus;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NightAuditLog extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'audit_date',
        'occupied_rooms_count',
        'total_room_revenue_posted',
        'total_tax_posted',
        'status',
        'executed_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'audit_date' => 'date:Y-m-d',
            'occupied_rooms_count' => 'integer',
            'total_room_revenue_posted' => 'decimal:2',
            'total_tax_posted' => 'decimal:2',
            'status' => NightAuditStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }
}
