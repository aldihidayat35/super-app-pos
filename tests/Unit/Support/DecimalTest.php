<?php

namespace Tests\Unit\Support;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_addition(): void
    {
        $this->assertSame('12.7500', Decimal::add('10.50', '2.25', 4));
        $this->assertSame('12.75', Decimal::add('10.50', '2.25', 2));
        $this->assertSame('0.00', Decimal::add('-5.50', '5.50', 2));
        $this->assertSame('1000000000000.00', Decimal::add('500000000000.00', '500000000000.00', 2));
    }

    public function test_subtraction(): void
    {
        $this->assertSame('8.2500', Decimal::sub('10.50', '2.25', 4));
        $this->assertSame('8.25', Decimal::sub('10.50', '2.25', 2));
        $this->assertSame('-11.00', Decimal::sub('-5.50', '5.50', 2));
    }

    public function test_multiplication(): void
    {
        $this->assertSame('150.00', Decimal::mul('100.00', '1.5', 2, 1, 2));
        $this->assertSame('0.3000', Decimal::mul('0.1000', '3', 4, 0, 4));
        $this->assertSame('30.0', Decimal::mul('0.3000', '100', 4, 0, 1));
        // Large values (billions) without overflow
        $this->assertSame('500000000000.00', Decimal::mul('250000000000.00', '2', 2, 0, 2));
    }

    public function test_division_and_rounding(): void
    {
        $this->assertSame('3.33', Decimal::div('10.00', '3.00', 2, 2, 2));
        $this->assertSame('2.50', Decimal::div('5.00', '2.00', 2, 2, 2));
        $this->assertSame('0.67', Decimal::div('2.00', '3.00', 2, 2, 2));
        $this->assertSame('0.3000', Decimal::div('6000.00', '20000.00', 2, 2, 4));

        // High margin scenario that previously caused integer overflow & TypeError
        $this->assertSame('0.4000', Decimal::div('100000.00', '250000.00', 4, 6, 4));
        $this->assertSame('0.5000', Decimal::div('50000000.00', '100000000.00', 2, 2, 4));
    }

    public function test_division_by_zero_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::div('100', '0');
    }

    public function test_comparison(): void
    {
        $this->assertSame(0, Decimal::compare('10.50', '10.500', 2));
        $this->assertSame(1, Decimal::compare('10.51', '10.50', 2));
        $this->assertSame(-1, Decimal::compare('10.49', '10.50', 2));
        $this->assertTrue(Decimal::isZero('0.0000'));
        $this->assertTrue(Decimal::isPositive('0.01'));
        $this->assertFalse(Decimal::isPositive('-0.01'));
    }

    public function test_normalize(): void
    {
        $this->assertSame('12.5000', Decimal::normalize('12.5', 4));
        $this->assertSame('12.56', Decimal::normalize('12.56789', 2));
        $this->assertSame('-5.00', Decimal::normalize('-5', 2));
    }
}
