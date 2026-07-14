<?php

namespace App\Console\Commands;

use App\Services\Notifications\DailyReportNotificationService;
use Illuminate\Console\Command;

class RunNotificationSchedulesCommand extends Command
{
    protected $signature = 'notifications:run-schedules {--sync : Kirim langsung tanpa queue untuk pengujian lokal}';

    protected $description = 'Menjalankan jadwal laporan dan alert notifikasi yang sudah jatuh tempo.';

    public function handle(DailyReportNotificationService $service): int
    {
        $schedules = $service->dueSchedules();
        $this->info('Jadwal jatuh tempo: '.$schedules->count());

        foreach ($schedules as $schedule) {
            $result = $service->runSchedule($schedule, queue: ! (bool) $this->option('sync'));
            $this->line(sprintf(
                '- %s: report #%d, queued %d, skipped %d',
                $schedule->name,
                $result['report']->id,
                $result['queued'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
