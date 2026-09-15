<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceService;
use Illuminate\Console\Command;

class GenerateAttendanceSchedulesCommand extends Command
{
    protected $signature = 'attendance:schedules-generate {--days=28}';

    protected $description = 'Membuat jadwal harian dari pola mingguan aktif';

    public function handle(AttendanceService $service): int
    {
        $count = $service->generateAllPatternSchedules(max(1, min(60, (int) $this->option('days'))));
        $this->info("Jadwal aktif dipastikan untuk {$count} tanggal.");

        return self::SUCCESS;
    }
}
