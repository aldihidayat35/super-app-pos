<?php

namespace Tests\Unit\Support;

use App\Support\QuantityFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuantityFormatterTest extends TestCase
{
    #[Test]
    public function it_hides_trailing_decimal_zeroes_for_quantities(): void
    {
        $this->assertSame('10', QuantityFormatter::format('10.0000'));
        $this->assertSame('1.250', QuantityFormatter::format('1250.0000'));
        $this->assertSame('0', QuantityFormatter::format('0.0000'));
    }

    #[Test]
    public function it_keeps_real_fractional_quantities_when_they_exist(): void
    {
        $this->assertSame('1,5', QuantityFormatter::format('1.5000'));
        $this->assertSame('1,2345', QuantityFormatter::format('1.234567'));
        $this->assertSame('-2,25', QuantityFormatter::format('-2.2500'));
    }

    #[Test]
    public function it_formats_input_values_without_locale_separators(): void
    {
        $this->assertSame('1250', QuantityFormatter::input('1250.0000'));
        $this->assertSame('1.5', QuantityFormatter::input('1.5000'));
    }
}
