<?php

namespace App\Services;

use App\Models\RoomRate;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Effective room pricing.
 *
 * Rule for a night (the date the guest sleeps):
 *   1. Take the active room_rates of the room type whose [start_date, end_date] (inclusive)
 *      contains the date and whose days_of_week (ISO 1-7, empty = all) contains its weekday.
 *   2. If several match, the most specific wins: shortest date range first, then a rate
 *      restricted to weekdays beats an unrestricted one, then the newest rate.
 *   3. If none match, the room type's base_price applies.
 *
 * Money is handled as integer cents. The check-out day is not a night.
 * The service reads rates explicitly by the room type's hotel, so it also works in
 * console code (night audit) without an active-tenant context.
 */
class PricingService
{
    /**
     * @return array{price_cents: int, rate_name: string|null, rate_id: int|null, is_override: bool}
     */
    public function rateFor(RoomType $type, CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return $this->resolve($type, $this->ratesBetween($type, $day, $day), $day);
    }

    /**
     * Itemised quote for a stay.
     *
     * @return array{
     *     nights: int,
     *     nightly: list<array{date: string, rate_cents: int, extra_cents: int, total_cents: int, rate_name: string|null, is_override: bool}>,
     *     subtotal_cents: int,
     *     extra_person_cents: int,
     *     total_cents: int
     * }
     */
    public function quoteStay(RoomType $type, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $guests = null): array
    {
        $start = CarbonImmutable::instance($checkIn)->startOfDay();
        $end = CarbonImmutable::instance($checkOut)->startOfDay();
        $nights = (int) round($start->diffInDays($end));

        if ($nights < 1) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }

        if ($guests !== null && $guests > $type->max_occupancy) {
            throw new InvalidArgumentException("{$type->name} sleeps at most {$type->max_occupancy} guests.");
        }

        $extraGuests = $guests === null ? 0 : max(0, $guests - $type->base_occupancy);
        $extraPerNight = $extraGuests * Money::toCents($type->extra_person_fee);

        $rates = $this->ratesBetween($type, $start, $end->subDay());

        $nightly = [];
        $subtotal = 0;

        for ($i = 0; $i < $nights; $i++) {
            $day = $start->addDays($i);
            $resolved = $this->resolve($type, $rates, $day);

            $nightly[] = [
                'date' => $day->toDateString(),
                'rate_cents' => $resolved['price_cents'],
                'extra_cents' => $extraPerNight,
                'total_cents' => $resolved['price_cents'] + $extraPerNight,
                'rate_name' => $resolved['rate_name'],
                'is_override' => $resolved['is_override'],
            ];

            $subtotal += $resolved['price_cents'];
        }

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'subtotal_cents' => $subtotal,
            'extra_person_cents' => $extraPerNight * $nights,
            'total_cents' => $subtotal + ($extraPerNight * $nights),
        ];
    }

    /**
     * Effective price of every day of a month (for the rate calendar).
     *
     * @return list<array{date: string, price_cents: int, rate_name: string|null, rate_id: int|null, is_override: bool}>
     */
    public function monthCalendar(RoomType $type, CarbonInterface $month): array
    {
        $first = CarbonImmutable::instance($month)->startOfMonth()->startOfDay();
        $last = $first->endOfMonth()->startOfDay();
        $rates = $this->ratesBetween($type, $first, $last);

        $days = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString()] + $this->resolve($type, $rates, $day);
        }

        return $days;
    }

    /** @return Collection<int, RoomRate> */
    private function ratesBetween(RoomType $type, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return RoomRate::query()
            ->withoutGlobalScopes()
            ->where('hotel_id', $type->hotel_id)
            ->where('room_type_id', $type->getKey())
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, RoomRate>  $rates
     * @return array{price_cents: int, rate_name: string|null, rate_id: int|null, is_override: bool}
     */
    private function resolve(RoomType $type, Collection $rates, CarbonImmutable $day): array
    {
        $matches = $rates
            ->filter(fn (RoomRate $rate): bool => $rate->start_date->startOfDay()->lte($day)
                && $rate->end_date->startOfDay()->gte($day)
                && $rate->appliesToWeekday($day->dayOfWeekIso))
            ->sort(function (RoomRate $a, RoomRate $b): int {
                $spanA = $a->start_date->diffInDays($a->end_date);
                $spanB = $b->start_date->diffInDays($b->end_date);

                return [$spanA, empty($a->days_of_week) ? 1 : 0, -$a->id]
                    <=> [$spanB, empty($b->days_of_week) ? 1 : 0, -$b->id];
            });

        $winner = $matches->first();

        if ($winner === null) {
            return [
                'price_cents' => Money::toCents($type->base_price),
                'rate_name' => null,
                'rate_id' => null,
                'is_override' => false,
            ];
        }

        return [
            'price_cents' => Money::toCents($winner->price_per_night),
            'rate_name' => $winner->rate_name,
            'rate_id' => $winner->id,
            'is_override' => true,
        ];
    }
}
