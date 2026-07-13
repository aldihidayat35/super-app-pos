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
