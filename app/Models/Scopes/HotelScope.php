<?php

namespace App\Models\Scopes;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applies WHERE hotel_id = <active hotel> to every tenant model.
 * Fails closed: with no active hotel the query returns nothing.
 */
class HotelScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $hotelId = Tenant::id();

        if ($hotelId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('hotel_id'), $hotelId);
    }
}
