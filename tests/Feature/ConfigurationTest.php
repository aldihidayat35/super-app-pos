<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function application_uses_the_indonesian_baseline(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('IDR', config('gudangtoko.currency'));
    }
}
