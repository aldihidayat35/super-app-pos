<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Cache::put('system.scheduler.last_run', now()->toDateTimeString(), now()->addMinutes(5));
})->everyMinute()->name('system-scheduler-heartbeat')->withoutOverlapping();

Schedule::command('notifications:run-schedules')
    ->everyMinute()
    ->timezone(config('notifications.timezone', 'Asia/Jakarta'))
    ->name('notification-schedule-runner')
    ->withoutOverlapping();

Schedule::command('checklists:generate')
    ->everyFifteenMinutes()
    ->timezone(config('work-checklists.timezone', 'Asia/Jakarta'))
    ->name('work-checklist-generator')
    ->withoutOverlapping();

Schedule::command('attendance:schedules-generate')
    ->dailyAt('00:05')
    ->timezone('Asia/Jakarta')
    ->name('attendance-schedule-generator')
    ->withoutOverlapping();

Schedule::command('staff-bonuses:calculate')
    ->dailyAt('01:15')
    ->timezone(config('staff-bonuses.timezone', 'Asia/Jakarta'))
    ->name('staff-bonus-calculator')
    ->withoutOverlapping();

if (config('security.backup.enabled')) {
    Schedule::command('system:encrypted-backup')
        ->dailyAt((string) config('security.backup.schedule_time', '02:30'))
        ->timezone(config('app.timezone', 'Asia/Jakarta'))
        ->name('system-encrypted-backup')
        ->withoutOverlapping();
}
