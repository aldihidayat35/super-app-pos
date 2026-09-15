<?php

namespace App\Console\Commands;

use App\Services\WorkChecklist\WorkChecklistService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateWorkChecklistsCommand extends Command
{
    protected $signature = 'checklists:generate {--date= : Waktu acuan ISO untuk pengujian atau proses ulang}';

    protected $description = 'Membuat checklist harian dan mingguan untuk akun internal aktif';

    public function handle(WorkChecklistService $service): int
    {
        $date = $this->option('date');
        $moment = is_string($date) && $date !== ''
            ? CarbonImmutable::parse($date, (string) config('work-checklists.timezone'))
            : null;
        $count = $service->generateCurrentForAll($moment);
        $this->info("Checklist aktif dipastikan untuk {$count} lingkup akun.");

        return self::SUCCESS;
    }
}
