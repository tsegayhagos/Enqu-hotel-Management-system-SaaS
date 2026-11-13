<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Exceptions\InvalidRoomTransition;
use App\Traits\BelongsToHotel;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToHotel, HasFactory;

    protected $fillable = [
        'room_type_id',
        'room_number',
        'floor_number',
        'status',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'floor_number' => 'integer',
            'status' => RoomStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /** Floor, then room number in natural order (2 before 10). */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('floor_number')->orderByRaw('LENGTH(room_number)')->orderBy('room_number');
    }

    // ---- State machine (Module 02) -----------------------------------------------------------

    /** Cleaning finished / quick-mark clean. */
    public function markAsClean(): static
    {
        return $this->transitionTo(RoomStatus::VacantClean);
    }

    /** A housekeeper has started working on the room. */
    public function startCleaning(): static
    {
        return $this->transitionTo(RoomStatus::Cleaning);
    }

    /** Guest left (check-out) or the room was used and needs service. */
    public function markAsDirty(): static
    {
        return $this->transitionTo(RoomStatus::VacantDirty);
    }

    /** Check-in. Only a vacant, clean room can be occupied. */
    public function setOccupied(): static
    {
        return $this->transitionTo(RoomStatus::Occupied);
    }

    /** Block the room (maintenance). */
    public function setOutOfOrder(): static
    {
        return $this->transitionTo(RoomStatus::OutOfOrder);
    }

    /**
     * Move the room to a new status, enforcing the state machine.
     *
     * @param  bool  $manual  true for hand-made changes (UI): occupancy can then not be touched.
     *
     * @throws InvalidRoomTransition
     */
    public function transitionTo(RoomStatus $to, bool $manual = false): static
    {
        return DB::transaction(function () use ($to, $manual): static {
            // Re-read under lock so two users cannot race the same room.
            $current = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $from = $current->status;

            if ($manual && ($from === RoomStatus::Occupied || $to === RoomStatus::Occupied)) {
                throw InvalidRoomTransition::manualOccupancyChange($this);
            }

            if (! $from->canTransitionTo($to)) {
                throw InvalidRoomTransition::notAllowed($this, $from, $to);
            }

            $this->forceFill(['status' => $to])->save();

            return $this;
        });
    }
}
