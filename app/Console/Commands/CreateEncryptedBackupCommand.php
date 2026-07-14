<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class CreateEncryptedBackupCommand extends Command
{
    protected $signature = 'system:encrypted-backup
        {--connection= : Nama koneksi database yang akan dibackup, default mengikuti database.default}
        {--dry-run : Validasi konfigurasi tanpa menjalankan mysqldump}
        {--keep-temp : Simpan file SQL sementara untuk investigasi lokal}';

    protected $description = 'Membuat backup database MySQL/MariaDB terenkripsi untuk kebutuhan disaster recovery.';

    public function handle(): int
    {
        $connectionName = $this->connectionName();

        if ($connectionName !== 'mysql') {
            $this->warn('Backup otomatis ini hanya mendukung koneksi mysql/mariadb.');

            return self::FAILURE;
        }

        $this->validateConfiguration($connectionName);

        if ($this->option('dry-run')) {
            $this->info('Konfigurasi backup terenkripsi valid. Dry-run selesai tanpa membuat file backup.');

            return self::SUCCESS;
        }

        $tmpPath = storage_path('app/private/backups/tmp/'.now()->format('YmdHis').'-database.sql');

        try {
            File::ensureDirectoryExists(dirname($tmpPath));
            $this->dumpDatabase($tmpPath, $connectionName);

            $encryptedPayload = Crypt::encryptString(File::get($tmpPath));
            $fileName = sprintf('%s-%s.sql.enc', config('app.name', 'gudangtoko'), now()->format('Ymd-His'));
            $target = trim((string) config('security.backup.path'), '/').'/'.$fileName;

            Storage::disk((string) config('security.backup.disk'))->put($target, $encryptedPayload);
            $this->pruneOldBackups();

            $this->info("Backup terenkripsi dibuat: {$target}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Backup gagal. Periksa log aplikasi dan pastikan mysqldump tersedia.');

            return self::FAILURE;
        } finally {
            if (! $this->option('keep-temp') && File::exists($tmpPath)) {
                File::delete($tmpPath);
            }
        }
    }

    private function connectionName(): string
    {
        return (string) ($this->option('connection') ?: config('database.default'));
    }

    private function validateConfiguration(string $connectionName): void
    {
        foreach (['host', 'database', 'username'] as $key) {
            if (blank(config("database.connections.{$connectionName}.{$key}"))) {
                throw new RuntimeException("Konfigurasi database {$connectionName}.{$key} wajib diisi.");
            }
        }

        if (blank(config('security.backup.disk')) || blank(config('security.backup.path'))) {
            throw new RuntimeException('Konfigurasi security.backup.disk dan security.backup.path wajib diisi.');
        }
    }

    private function dumpDatabase(string $tmpPath, string $connectionName): void
    {
        $connection = config("database.connections.{$connectionName}");
        $command = [
            (string) config('security.backup.mysqldump_binary', 'mysqldump'),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--host='.(string) $connection['host'],
            '--port='.(string) ($connection['port'] ?? 3306),
            '--user='.(string) $connection['username'],
        ];

        if (filled($connection['password'] ?? null)) {
            $command[] = '--password='.(string) $connection['password'];
        }

        $command[] = (string) $connection['database'];

        $handle = fopen($tmpPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara backup.');
        }

        try {
            $process = new Process($command);
            $process->setTimeout(3600);
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                }
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException($process->getErrorOutput() ?: 'mysqldump gagal tanpa pesan error.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function pruneOldBackups(): void
    {
        $retentionDays = max(1, (int) config('security.backup.retention_days', 14));
        $disk = Storage::disk((string) config('security.backup.disk'));
        $path = trim((string) config('security.backup.path'), '/');
        $threshold = now()->subDays($retentionDays)->getTimestamp();

        foreach ($disk->files($path) as $file) {
            if (! str_ends_with($file, '.sql.enc')) {
                continue;
            }

            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
            }
        }
    }
}
