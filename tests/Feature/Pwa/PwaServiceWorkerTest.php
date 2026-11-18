<?php

namespace Tests\Feature\Pwa;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PwaServiceWorkerTest extends TestCase
{
    #[Test]
    public function service_worker_file_exists_and_contains_expected_lifecycle(): void
    {
        $swPath = public_path('sw.js');
        self::assertFileExists($swPath, 'File public/sw.js tidak ditemukan.');

        $swContent = (string) file_get_contents($swPath);
        self::assertStringContainsString('STATIC_CACHE', $swContent);
        self::assertStringContainsString('/offline', $swContent);
        self::assertStringContainsString('addEventListener(\'install\'', $swContent);
        self::assertStringContainsString('addEventListener(\'activate\'', $swContent);
        self::assertStringContainsString('addEventListener(\'fetch\'', $swContent);
        self::assertStringContainsString('SKIP_WAITING', $swContent);
    }

    #[Test]
    public function offline_fallback_route_returns_ok_and_presents_offline_interface(): void
    {
        $response = $this->get(route('pwa.offline'));

        $response->assertOk();
        $response->assertSee('Koneksi Terputus');
        $response->assertSee('Mode Offline');
        $response->assertSee('Coba Sambungkan Ulang');
    }

    #[Test]
    public function layout_renders_service_worker_registration_and_update_banner(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('pwa-update-banner', false);
        $response->assertSee('navigator.serviceWorker.register(\'/sw.js\'', false);
        $response->assertSee('btn-reload-pwa', false);
    }
}
