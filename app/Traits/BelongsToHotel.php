<?php

namespace App\Traits;

use App\Models\Hotel;
use App\Models\Scopes\HotelScope;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Multi-tenant scoping for hotel-owned models.
 *
 * - Automatically adds WHERE hotel_id = active hotel (fails closed if no active hotel)
 * - Automatically sets hotel_id on creation
 * - Refuses to create a record for a hotel other than the active one
 */
trait BelongsToHotel
{
    public static function bootBelongsToHotel(): void
    {
        static::addGlobalScope(new HotelScope);

        static::creating(function (Model $model): void {
            $activeId = Tenant::id();
            $hotelId = $model->getAttribute('hotel_id');

            if ($hotelId === null) {
                if ($activeId === null) {
                    throw new LogicException(
                        'Cannot create '.class_basename($model).' without an active hotel. '
                        .'Use Tenant::runAs($hotelId, ...) in console code.'
                    );
                }

                $model->setAttribute('hotel_id', $activeId);

                return;
            }

            if ($activeId !== null && (int) $hotelId !== $activeId) {
                throw new LogicException('Cross-tenant write blocked: hotel_id does not match the active hotel.');
            }
        });
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
