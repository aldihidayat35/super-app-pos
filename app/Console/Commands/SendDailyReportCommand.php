<?php

namespace App\Console\Commands;

use App\Models\NotificationSchedule;
use App\Services\Notifications\DailyReportNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDailyReportCommand extends Command
{
    protected $signature = 'reports:send-daily {--date=} {--schedule_id=} {--sync : Kirim langsung tanpa queue untuk pengujian lokal}';

    protected $description = 'Membuat snapshot laporan harian dan mengirim ke penerima terkonfigurasi.';

    public function handle(DailyReportNotificationService $service): int
    {
        $query = NotificationSchedule::query()
            ->where('is_active', true)
            ->where('report_type', 'daily_report')
            ->when($this->option('schedule_id'), fn ($query, mixed $id) => $query->whereKey((int) $id));

        $date = $this->option('date') ? Carbon::parse((string) $this->option('date'), 'Asia/Jakarta') : null;
        $count = 0;

        foreach ($query->get() as $schedule) {
            $result = $service->runSchedule($schedule, $date, ! (bool) $this->option('sync'));
            $this->line(sprintf(
                'Report #%d %s: queued %d, skipped %d',
                $result['report']->id,
                $result['report']->reportDate()->toDateString(),
                $result['queued'],
                $result['skipped'],
            ));
            $count++;
        }

        $this->info('Total jadwal diproses: '.$count);

        return self::SUCCESS;
    }
}
