<?php

namespace Tests\Feature\Pwa;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    #[Test]
    public function pwa_manifest_file_exists_and_is_valid_json(): void
    {
        $manifestPath = public_path('manifest.webmanifest');
        self::assertFileExists($manifestPath, 'File manifest.webmanifest tidak ditemukan di public/');

        $content = (string) file_get_contents($manifestPath);
        $json = json_decode($content, true);

        self::assertIsArray($json, 'File manifest.webmanifest harus berupa JSON yang valid.');
        self::assertSame('GudangToko - Sistem Manajemen Terpadu', $json['name']);
        self::assertSame('GudangToko', $json['short_name']);
        self::assertSame('standalone', $json['display']);
        self::assertSame('/dashboard', $json['start_url']);
        self::assertNotEmpty($json['icons'], 'Manifest harus memiliki konfigurasi icons.');

        $purposes = array_column($json['icons'], 'purpose');
        self::assertContains('maskable', $purposes, 'Harus ada icon dengan purpose maskable.');
    }

    #[Test]
    public function pwa_icon_files_exist_in_public_directory(): void
    {
        foreach ([
            'assets/media/pwa/icon-192x192.png',
            'assets/media/pwa/icon-512x512.png',
            'assets/media/pwa/icon-maskable-512x512.png',
            'assets/media/pwa/apple-touch-icon.png',
        ] as $iconRelPath) {
            self::assertFileExists(public_path($iconRelPath), "File icon {$iconRelPath} tidak ditemukan.");
        }
    }

    #[Test]
    public function web_pages_include_pwa_manifest_and_apple_meta_tags(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('manifest.webmanifest', false);
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('apple-touch-icon', false);
    }
}
