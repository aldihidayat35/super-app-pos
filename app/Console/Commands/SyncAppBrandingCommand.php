<?php

namespace App\Console\Commands;

use App\Services\System\AppBrandingService;
use Illuminate\Console\Command;

class SyncAppBrandingCommand extends Command
{
    protected $signature = 'app:sync-branding';

    protected $description = 'Sinkronisasikan logo perusahaan dan nama bisnis ke Favicon, icon PWA, dan Web App Manifest';

    public function handle(AppBrandingService $brandingService): int
    {
        $this->info('Menyinkronkan branding perusahaan ke favicon dan PWA assets...');

        $success = $brandingService->sync();

        if ($success) {
            $this->info('Branding aplikasi berhasil disinkronkan.');

            return self::SUCCESS;
        }

        $this->error('Gagal menyinkronkan branding aplikasi.');

        return self::FAILURE;
    }
}
