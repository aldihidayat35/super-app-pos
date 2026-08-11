<?php

namespace Tests\Unit\Support;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionViteAssetTest extends TestCase
{
    #[Test]
    public function production_ignores_a_stale_public_hot_file(): void
    {
        $this->withVite();

        $originalEnvironment = app()->environment();
        $vite = app(ViteManager::class);

        try {
            app()->instance('env', 'production');
            $vite->useHotFile(public_path('hot'));

            (new AppServiceProvider(app()))->boot();

            self::assertSame(
                storage_path('framework/vite-production.hot'),
                $vite->hotFile(),
            );
        } finally {
            app()->instance('env', $originalEnvironment);
            $vite->useHotFile(public_path('hot'));
        }
    }

    #[Test]
    public function public_domain_uses_built_assets_even_if_environment_is_misconfigured_as_local(): void
    {
        $this->withVite();

        $originalEnvironment = app()->environment();
        $originalRequest = app('request');
        $vite = app(ViteManager::class);

        try {
            app()->instance('env', 'local');
            app()->instance('request', Request::create('https://super-app-kedaung.demokan.online/owner/dashboard'));
            $vite->useHotFile(public_path('hot'));

            (new AppServiceProvider(app()))->boot();

            self::assertSame(
                storage_path('framework/vite-production.hot'),
                $vite->hotFile(),
            );
        } finally {
            app()->instance('env', $originalEnvironment);
            app()->instance('request', $originalRequest);
            $vite->useHotFile(public_path('hot'));
        }
    }
}
