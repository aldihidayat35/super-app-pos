<?php

namespace App\Console\Commands;

use App\Enums\StaffBonusPeriodStatus;
use App\Models\StaffBonusPeriod;
use App\Services\StaffBonus\StaffBonusService;
use Illuminate\Console\Command;

class CalculateStaffBonusesCommand extends Command
{
    protected $signature = 'staff-bonuses:calculate';

    protected $description = 'Memperbarui pratinjau target dan bonus staf yang sedang berjalan';

    public function handle(StaffBonusService $service): int
    {
        StaffBonusPeriod::query()->whereIn('status', [StaffBonusPeriodStatus::ACTIVE->value, StaffBonusPeriodStatus::CALCULATING->value, StaffBonusPeriodStatus::REJECTED->value])
            ->chunkById(50, function ($periods) use ($service): void {
                foreach ($periods as $period) {
                    $service->calculate($period);
                }
            });
        $this->info('Pratinjau bonus staf berhasil diperbarui.');

        return self::SUCCESS;
    }
}
