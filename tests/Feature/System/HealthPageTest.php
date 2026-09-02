<?php

namespace Tests\Feature\System;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthPageTest extends TestCase
{
    #[Test]
    public function local_environment_can_view_the_health_page(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $response = $this->get(route('system.health'))
            ->assertOk()
            ->assertSee('SYS-01 — Pemeriksaan database, cache, session, queue, scheduler, storage, permission folder, versi aplikasi, dan waktu server tanpa menampilkan secret.')
            ->assertSee('Koneksi database berhasil.');

        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);

        $pageTitles = $xpath->query('//h1[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__title ")]');
        $pageDescriptions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__description ")]');
        $breadcrumbs = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " breadcrumb ")]');
        $headerActions = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " gt-page-header__actions ")]//*[contains(normalize-space(.), "Refresh")]');

        $this->assertCount(1, $pageTitles);
        $this->assertSame('Kesehatan Sistem', trim($pageTitles->item(0)->textContent));
        $this->assertCount(1, $pageDescriptions);
        $this->assertCount(0, $breadcrumbs);
        $this->assertCount(1, $headerActions);
    }

    #[Test]
    public function unauthenticated_user_cannot_view_health_page_outside_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get(route('system.health'))->assertForbidden();
    }
}
