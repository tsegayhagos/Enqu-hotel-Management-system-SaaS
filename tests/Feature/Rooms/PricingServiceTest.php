<?php

namespace Tests\Feature\Rooms;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\PricingService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Calendar reference (2026): Tue 1 Dec, Thu 3 Dec, Fri 4 Dec, Sat 5 Dec, Sun 6 Dec, Mon 7 Dec.
 * Base price in these tests is 100.00 (= 10000 cents).
 */
class PricingServiceTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $type;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = $this->makeHotel();
        $this->type = $this->makeRoomType($this->hotel, [
            'base_price' => '100.00',
            'base_occupancy' => 2,
            'max_occupancy' => 4,
            'extra_person_fee' => '15.00',
        ]);
        $this->pricing = app(PricingService::class);
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    private function weekendRate(string $price = '130.00', string $from = '2026-01-01', string $to = '2026-12-31'): void
    {
        $this->makeRate($this->hotel, $this->type, [
            'rate_name' => 'Weekend Surcharge', 'start_date' => $from, 'end_date' => $to,
            'price_per_night' => $price, 'days_of_week' => [5, 6],
        ]);
    }

    public function test_base_price_applies_when_no_rate_matches(): void
    {
        $rate = $this->pricing->rateFor($this->type, $this->day('2026-12-01'));

        $this->assertSame(10000, $rate['price_cents']);
        $this->assertFalse($rate['is_override']);
        $this->assertNull($rate['rate_name']);
    }

    public function test_weekend_override_only_applies_on_selected_weekdays(): void
    {
        $this->weekendRate();

        $this->assertSame(13000, $this->pricing->rateFor($this->type, $this->day('2026-12-04'))['price_cents']); // Fri
        $this->assertSame(13000, $this->pricing->rateFor($this->type, $this->day('2026-12-05'))['price_cents']); // Sat
        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2026-12-06'))['price_cents']); // Sun
        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2026-12-07'))['price_cents']); // Mon
    }

    public function test_holiday_season_range_is_inclusive_on_both_ends(): void
    {
        $this->makeRate($this->hotel, $this->type, [
            'rate_name' => 'Peak Season', 'start_date' => '2026-12-20', 'end_date' => '2027-01-05', 'price_per_night' => '200.00',
        ]);

        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2026-12-19'))['price_cents']);
        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2026-12-20'))['price_cents']);
        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2026-12-25'))['price_cents']);
        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2027-01-05'))['price_cents']);
        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2027-01-06'))['price_cents']);
    }

    public function test_narrowest_date_range_wins(): void
    {
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Peak', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '200.00']);
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Christmas', 'start_date' => '2026-12-24', 'end_date' => '2026-12-26', 'price_per_night' => '300.00']);

        $christmas = $this->pricing->rateFor($this->type, $this->day('2026-12-25'));

        $this->assertSame(30000, $christmas['price_cents']);
        $this->assertSame('Christmas', $christmas['rate_name']);
        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2026-12-10'))['price_cents']);
    }

    public function test_weekday_restricted_rate_beats_an_equal_range_all_days_rate(): void
    {
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Peak', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '200.00']);
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'December weekends', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '250.00', 'days_of_week' => [5, 6]]);

        $this->assertSame(25000, $this->pricing->rateFor($this->type, $this->day('2026-12-04'))['price_cents']); // Fri
        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2026-12-01'))['price_cents']); // Tue
    }

    public function test_a_narrow_season_beats_a_year_long_weekend_rate(): void
    {
        $this->weekendRate('130.00');
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Peak', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '200.00']);

        $this->assertSame(20000, $this->pricing->rateFor($this->type, $this->day('2026-12-05'))['price_cents']); // Sat in December
        $this->assertSame(13000, $this->pricing->rateFor($this->type, $this->day('2026-11-07'))['price_cents']); // Sat in November
    }

    public function test_inactive_rates_are_ignored(): void
    {
        $this->makeRate($this->hotel, $this->type, ['start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '999.00', 'is_active' => false]);

        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2026-12-10'))['price_cents']);
    }

    public function test_rates_of_other_room_types_and_hotels_never_apply(): void
    {
        $otherType = $this->makeRoomType($this->hotel, ['base_price' => '100.00']);
        $this->makeRate($this->hotel, $otherType, ['start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '777.00']);

        $otherHotel = $this->makeHotel();
        $foreignType = $this->makeRoomType($otherHotel);
        $this->makeRate($otherHotel, $foreignType, ['start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_per_night' => '888.00']);

        $this->assertSame(10000, $this->pricing->rateFor($this->type, $this->day('2026-12-10'))['price_cents']);
    }

    public function test_pricing_works_without_an_active_tenant_context(): void
    {
        $this->weekendRate();

        // Called from console code (night audit) - no session, no Tenant::runAs.
        $this->assertSame(13000, $this->pricing->rateFor($this->type, $this->day('2026-12-04'))['price_cents']);
    }

    public function test_stay_quote_adds_every_night_and_excludes_the_checkout_day(): void
    {
        $this->weekendRate();

        // Thu 3, Fri 4, Sat 5, Sun 6 -> 100 + 130 + 130 + 100; checkout Mon 7 is not a night.
        $quote = $this->pricing->quoteStay($this->type, $this->day('2026-12-03'), $this->day('2026-12-07'));

        $this->assertSame(4, $quote['nights']);
        $this->assertSame(46000, $quote['total_cents']);
        $this->assertSame(
            ['2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06'],
            array_column($quote['nightly'], 'date'),
        );
        $this->assertSame([10000, 13000, 13000, 10000], array_column($quote['nightly'], 'rate_cents'));
    }

    public function test_one_night_stay(): void
    {
        $this->weekendRate();

        $quote = $this->pricing->quoteStay($this->type, $this->day('2026-12-04'), $this->day('2026-12-05'));

        $this->assertSame(1, $quote['nights']);
        $this->assertSame(13000, $quote['total_cents']);
    }

    public function test_stay_spanning_two_seasons(): void
    {
        $this->makeRate($this->hotel, $this->type, ['rate_name' => 'Peak', 'start_date' => '2026-12-20', 'end_date' => '2027-01-05', 'price_per_night' => '200.00']);

        // 18 Dec, 19 Dec at base; 20 Dec, 21 Dec at peak.
        $quote = $this->pricing->quoteStay($this->type, $this->day('2026-12-18'), $this->day('2026-12-22'));

        $this->assertSame(10000 + 10000 + 20000 + 20000, $quote['total_cents']);
    }

    public function test_extra_person_fee_applies_beyond_base_occupancy_per_night(): void
    {
        // base occupancy 2, fee 15.00 per extra person per night.
        $three = $this->pricing->quoteStay($this->type, $this->day('2026-12-01'), $this->day('2026-12-03'), 3);
        $four = $this->pricing->quoteStay($this->type, $this->day('2026-12-01'), $this->day('2026-12-03'), 4);
        $two = $this->pricing->quoteStay($this->type, $this->day('2026-12-01'), $this->day('2026-12-03'), 2);

        $this->assertSame(20000, $two['total_cents']);
        $this->assertSame(20000 + (1500 * 2), $three['total_cents']);
        $this->assertSame(20000 + (3000 * 2), $four['total_cents']);
        $this->assertSame(3000, $three['extra_person_cents']);
    }

    public function test_more_guests_than_maximum_occupancy_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pricing->quoteStay($this->type, $this->day('2026-12-01'), $this->day('2026-12-03'), 5);
    }

    public function test_checkout_must_be_after_checkin(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->pricing->quoteStay($this->type, $this->day('2026-12-03'), $this->day('2026-12-03'));
    }

    public function test_month_calendar_lists_every_day_with_override_flags(): void
    {
        $this->weekendRate('130.00', '2026-12-01', '2026-12-31');

        $calendar = $this->pricing->monthCalendar($this->type, $this->day('2026-12-15'));

        $this->assertCount(31, $calendar);
        $this->assertSame('2026-12-01', $calendar[0]['date']);
        $this->assertFalse($calendar[2]['is_override']); // Thu 3
        $this->assertTrue($calendar[3]['is_override']);  // Fri 4
        $this->assertSame(13000, $calendar[4]['price_cents']); // Sat 5
    }

    public function test_money_helpers_never_use_floats(): void
    {
        $this->assertSame(12050, Money::toCents('120.50'));
        $this->assertSame(5, Money::toCents('0.05'));
        $this->assertSame(10000, Money::toCents('100'));
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame('120.50', Money::fromCents(12050));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('-1.20', Money::fromCents(-120));

        $this->expectException(InvalidArgumentException::class);
        Money::toCents('12,50');
    }
}
