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

        $this->get(route('system.health'))
            ->assertOk()
            ->assertSee('Kesehatan Sistem')
            ->assertSee('Koneksi database berhasil.');
    }

    #[Test]
    public function unauthenticated_user_cannot_view_health_page_outside_local(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get(route('system.health'))->assertForbidden();
    }
}
