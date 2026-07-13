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
            'storage' => $this->storage(),
            'queue' => $this->queue(),
            'scheduler' => $this->scheduler(),
            'application' => [
                'status' => 'ok',
                'message' => sprintf('Laravel %s, PHP %s', app()->version(), PHP_VERSION),
            ],
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
}
