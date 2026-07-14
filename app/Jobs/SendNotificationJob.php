<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $notificationLogId) {}

    public function handle(NotificationDispatchService $dispatcher): void
    {
        $log = NotificationLog::query()->findOrFail($this->notificationLogId);
        $dispatcher->send($log);
    }
}
