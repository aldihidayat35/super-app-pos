<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final class ApplicationLogService
{
    /** @return list<array{line: int, level: string, message: string}> */
    public function recentLines(int $limit = 100): array
    {
        $file = storage_path('logs/laravel.log');

        if (! File::exists($file)) {
            return [];
        }

        $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], -$limit);

        return collect($lines)
            ->values()
            ->map(fn (string $line, int $index): array => [
                'line' => $index + 1,
                'level' => $this->level($line),
                'message' => $this->sanitize($line),
            ])
            ->all();
    }

    /** @return list<object> */
    public function failedJobs(int $limit = 20): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
            ->latest('failed_at')
            ->limit($limit)
            ->get()
            ->map(function (object $job): object {
                $job->exception = $this->sanitize(mb_substr((string) $job->exception, 0, 1200));

                return $job;
            })
            ->all();
    }

    private function level(string $line): string
    {
        if (preg_match('/\\.(emergency|alert|critical|error|warning|notice|info|debug):/i', $line, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return 'info';
    }

    private function sanitize(string $value): string
    {
        $patterns = [
            '/(password|passwd|pwd|token|secret|api[_-]?key|authorization)(["\'\\s:=]+)([^"\'\\s,}]+)/i',
            '/(bearer\\s+)[a-z0-9._\\-]+/i',
        ];

        return preg_replace($patterns, '$1$2[REDACTED]', $value) ?: '[REDACTED]';
    }
}
