<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class HealthCheckService
{
    /** @return array<string, array{status: string, message: string}> */
    public function run(): array
    {
        return [
            'database' => $this->database(),
            'cache' => $this->cache(),
            'session' => $this->session(),
            'storage' => $this->storage(),
            'folder_permissions' => $this->folderPermissions(),
            'queue' => $this->queue(),
            'scheduler' => $this->scheduler(),
            'application' => $this->application(),
            'server_time' => $this->serverTime(),
        ];
    }

    /** @return array{status: string, message: string} */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok', 'message' => 'Koneksi database berhasil.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'error', 'message' => 'Koneksi database gagal. Periksa log aplikasi.'];
        }
    }

    /** @return array{status: string, message: string} */
    private function cache(): array
    {
        try {
            $key = 'system.health.'.str()->uuid()->toString();
            Cache::put($key, 'ok', now()->addMinute());
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return [
                'status' => $ok ? 'ok' : 'warning',
                'message' => $ok
                    ? 'Cache dapat ditulis dan dibaca.'
                    : 'Cache tidak mengembalikan nilai yang ditulis. Periksa driver cache.',
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'error', 'message' => 'Cache gagal diuji. Periksa log aplikasi.'];
        }
    }

    /** @return array{status: string, message: string} */
    private function session(): array
    {
        $driver = (string) config('session.driver');
        $secure = (bool) config('session.secure');
        $sameSite = (string) config('session.same_site');

        return [
            'status' => app()->environment('production') && ! $secure ? 'warning' : 'ok',
            'message' => "Session menggunakan driver {$driver}, same-site {$sameSite}. Cookie secure wajib aktif di production HTTPS.",
        ];
    }

    /** @return array{status: string, message: string} */
    private function storage(): array
    {
        $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $available = Storage::disk('public')->exists('.gitignore') || is_dir(storage_path('app/public'));

        return [
            'status' => $linked && $available ? 'ok' : 'warning',
            'message' => $linked && $available
                ? 'Disk public tersedia dan storage link terdeteksi.'
                : 'Jalankan php artisan storage:link dan periksa storage/app/public.',
        ];
    }

    /** @return array{status: string, message: string} */
    private function queue(): array
    {
        $connection = (string) config('queue.default');

        return [
            'status' => in_array($connection, ['sync', 'null'], true) ? 'warning' : 'ok',
            'message' => "Queue menggunakan koneksi {$connection}. Status worker perlu dipantau oleh process manager.",
        ];
    }

    /** @return array{status: string, message: string} */
    private function scheduler(): array
    {
        $lastRun = Cache::get('system.scheduler.last_run');

        return [
            'status' => $lastRun ? 'ok' : 'warning',
            'message' => $lastRun
                ? "Heartbeat scheduler terakhir: {$lastRun}."
                : 'Heartbeat belum tersedia. Jalankan scheduler lalu muat ulang halaman.',
        ];
    }

    /** @return array{status: string, message: string} */
    private function folderPermissions(): array
    {
        $paths = [
            'storage/app' => storage_path('app'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];

        $unwritable = [];

        foreach ($paths as $label => $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $unwritable[] = $label;
            }
        }

        return [
            'status' => $unwritable === [] ? 'ok' : 'error',
            'message' => $unwritable === []
                ? 'Folder runtime utama dapat ditulis.'
                : 'Folder perlu diperbaiki permission: '.implode(', ', $unwritable).'.',
        ];
    }

    /** @return array{status: string, message: string} */
    private function application(): array
    {
        $debugEnabled = (bool) config('app.debug');
        $environment = (string) app()->environment();

        return [
            'status' => $debugEnabled && $environment === 'production' ? 'error' : ($debugEnabled ? 'warning' : 'ok'),
            'message' => sprintf(
                'Laravel %s, PHP %s, environment %s, debug %s.',
                app()->version(),
                PHP_VERSION,
                $environment,
                $debugEnabled ? 'aktif' : 'nonaktif'
            ),
        ];
    }

    /** @return array{status: string, message: string} */
    private function serverTime(): array
    {
        return [
            'status' => config('app.timezone') === 'Asia/Jakarta' ? 'ok' : 'warning',
            'message' => 'Waktu server aplikasi: '.now()->format('d/m/Y H:i:s').' ('.config('app.timezone').').',
        ];
    }
}
