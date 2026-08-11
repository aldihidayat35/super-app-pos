<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VersionedAssetTest extends TestCase
{
    #[Test]
    public function existing_public_asset_receives_a_file_version(): void
    {
        $url = versioned_asset('assets/css/ki-icons-fallback.css');

        $this->assertStringContainsString('/assets/css/ki-icons-fallback.css?v=', $url);
    }

    #[Test]
    public function missing_public_asset_does_not_raise_an_error(): void
    {
        $url = versioned_asset('assets/css/file-yang-tidak-ada.css');

        $this->assertStringEndsWith('/assets/css/file-yang-tidak-ada.css', $url);
        $this->assertStringNotContainsString('?v=', $url);
    }
}
