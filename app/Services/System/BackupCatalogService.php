<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

final class BackupCatalogService
{
    /** @return list<array{path: string, encoded: string, name: string, size: int, human_size: string, last_modified: int, last_modified_label: string, checksum: string, status: string}> */
    public function list(): array
    {
        $disk = Storage::disk($this->disk());
        $path = $this->path();

        return collect($disk->files($path))
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.enc') || str_ends_with($file, '.zip.enc'))
            ->map(function (string $file) use ($disk): array {
                $contents = (string) $disk->get($file);
                $modified = $disk->lastModified($file);

                return [
                    'path' => $file,
                    'encoded' => rtrim(strtr(base64_encode($file), '+/', '-_'), '='),
                    'name' => basename($file),
                    'size' => $disk->size($file),
                    'human_size' => Number::fileSize($disk->size($file)),
                    'last_modified' => $modified,
                    'last_modified_label' => date('d/m/Y H:i:s', $modified),
                    'checksum' => hash('sha256', $contents),
                    'status' => 'encrypted',
                ];
            })
            ->sortByDesc('last_modified')
            ->values()
            ->all();
    }

    public function decode(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        $path = $this->path().'/';

        if (! str_starts_with($decoded, $path) || (! str_ends_with($decoded, '.sql.enc') && ! str_ends_with($decoded, '.zip.enc'))) {
            return null;
        }

        return $decoded;
    }

    public function disk(): string
    {
        return (string) config('security.backup.disk', 'local');
    }

    public function path(): string
    {
        return trim((string) config('security.backup.path', 'private/backups'), '/');
    }
}
