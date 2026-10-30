<?php

namespace App\Traits;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToHotel
{
    /**
     * Boot the trait to automatically scope queries and set hotel_id on creation.
     */
    protected static function bootBelongsToHotel(): void
    {
        static::addGlobalScope('hotel', function (Builder $builder): void {
            if (session()->has('active_hotel_id')) {
                $builder->where($builder->getModel()->getTable().'.hotel_id', session('active_hotel_id'));
            }
        });

        static::creating(function ($model): void {
            if (empty($model->hotel_id) && session()->has('active_hotel_id')) {
                $model->hotel_id = session('active_hotel_id');
            }
        });
    }

    /**
     * Get the hotel property that owns this record.
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
