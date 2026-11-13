<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceTicketStatus;
use App\Models\Scopes\HotelScope;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceTicket extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_id',
        'location_label',
        'reported_by_user_id',
        'assigned_to_user_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'blocks_room',
        'estimated_cost',
        'actual_cost',
        'resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceTicketStatus::class,
            'blocks_room' => 'boolean',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withoutGlobalScope(HotelScope::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenanceTicketPhoto::class);
    }

    public function canTransitionTo(MaintenanceTicketStatus $to): bool
    {
        return $this->status->canTransitionTo($to);
    }

    public function isBlockingActive(): bool
    {
        return $this->blocks_room && in_array($this->status, [
            MaintenanceTicketStatus::Open,
            MaintenanceTicketStatus::InProgress,
        ], true);
    }
}
