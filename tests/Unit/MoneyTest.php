<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_to_cents_converts_decimal_strings(): void
    {
        $this->assertSame(10000, Money::toCents('100.00'));
        $this->assertSame(2550, Money::toCents('25.5'));
        $this->assertSame(500, Money::toCents('5'));
        $this->assertSame(0, Money::toCents('0.00'));
        $this->assertSame(-1500, Money::toCents('-15.00'));
    }

    public function test_from_cents_formats_proper_decimals(): void
    {
        $this->assertSame('100.00', Money::fromCents(10000));
        $this->assertSame('25.50', Money::fromCents(2550));
        $this->assertSame('0.05', Money::fromCents(5));
        $this->assertSame('0.00', Money::fromCents(0));
        $this->assertSame('-15.00', Money::fromCents(-1500));
    }
}
