<?php

namespace Tests\Unit\Support;

use App\Support\CurrencyFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrencyFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_rupiah_without_decimal_by_default(): void
    {
        $formatted = CurrencyFormatter::rupiah('1250000.00');

        $this->assertStringContainsString('1.250.000', str_replace("\u{00A0}", ' ', $formatted));
    }
}
