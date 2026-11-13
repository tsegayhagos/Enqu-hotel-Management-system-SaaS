<?php

namespace App\Models;

use App\Enums\HousekeepingPriority;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Models\Scopes\HotelScope;
use App\Traits\BelongsToHotel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingTask extends Model
{
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_id',
        'reservation_id',
        'assigned_to_user_id',
        'task_type',
        'priority',
        'status',
        'due_date',
        'started_at',
        'completed_at',
        'inspected_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => HousekeepingTaskType::class,
            'priority' => HousekeepingPriority::class,
            'status' => HousekeepingTaskStatus::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withoutGlobalScope(HotelScope::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function canTransitionTo(HousekeepingTaskStatus $to): bool
    {
        return $this->status->canTransitionTo($to);
    }
}
