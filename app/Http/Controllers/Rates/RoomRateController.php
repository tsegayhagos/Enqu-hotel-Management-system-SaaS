<?php

namespace App\Http\Controllers\Rates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rates\RoomRateRequest;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\PricingService;
use App\Support\Money;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Calendar rate-override manager (seasons, weekends, holidays). */
class RoomRateController extends Controller
{
    public function __construct(private readonly PricingService $pricing) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'room_type_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $types = RoomType::query()->orderBy('name')->get();
        $type = $types->firstWhere('id', (int) $request->input('room_type_id')) ?? $types->first();

        $timezone = Tenant::hotel()?->timezone ?? 'UTC';
        $month = $request->filled('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->string('month')->toString().'-01', $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfMonth();

        $calendar = [];
        $rates = [];

        if ($type !== null) {
            $calendar = array_map(fn (array $day): array => [
                'date' => $day['date'],
                'price' => Money::fromCents($day['price_cents']),
                'rate_name' => $day['rate_name'],
                'is_override' => $day['is_override'],
            ], $this->pricing->monthCalendar($type, $month));

            $rates = RoomRate::query()
                ->where('room_type_id', $type->id)
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (RoomRate $rate): array => [
                    'id' => $rate->id,
                    'room_type_id' => $rate->room_type_id,
                    'rate_name' => $rate->rate_name,
                    'start_date' => $rate->start_date->toDateString(),
                    'end_date' => $rate->end_date->toDateString(),
                    'price_per_night' => $rate->price_per_night,
                    'days_of_week' => $rate->days_of_week ?? [],
                    'is_active' => $rate->is_active,
                ])
                ->all();
        }

        return Inertia::render('Rates/Index', [
            'roomTypes' => $types->map(fn (RoomType $t): array => [
                'value' => $t->id,
                'label' => $t->name,
                'base_price' => $t->base_price,
            ])->values(),
            'selectedTypeId' => $type?->id,
            'month' => $month->format('Y-m'),
            'calendar' => $calendar,
            'rates' => $rates,
        ]);
    }

    public function store(RoomRateRequest $request): RedirectResponse
    {
        RoomRate::create($this->payload($request));

        return back()->with('success', 'Rate saved.');
    }

    public function update(RoomRateRequest $request, RoomRate $rate): RedirectResponse
    {
        $rate->update($this->payload($request));

        return back()->with('success', 'Rate updated.');
    }

    public function destroy(RoomRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', 'Rate deleted.');
    }

    /** @return array<string, mixed> */
    private function payload(RoomRateRequest $request): array
    {
        $data = $request->validated();

        if (! empty($data['days_of_week'])) {
            $days = array_map('intval', $data['days_of_week']);
            sort($days);
            // Selecting all seven days is the same as "every day".
            $data['days_of_week'] = count($days) === 7 ? null : $days;
        } else {
            $data['days_of_week'] = null;
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}
