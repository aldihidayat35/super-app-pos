<?php

namespace App\Services\Notifications;

use App\Enums\DailyReportStatus;
use App\Enums\NotificationChannelType;
use App\Jobs\SendNotificationJob;
use App\Models\DailyReport;
use App\Models\NotificationRecipient;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\SecureReportToken;
use App\Models\User;
use App\Services\Reports\ReportMetricService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyReportNotificationService
{
    public function __construct(
        private readonly ReportMetricService $reports,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly NotificationDispatchService $dispatcher,
    ) {}

    /**
     * @return Collection<int, NotificationSchedule>
     */
    public function dueSchedules(?Carbon $now = null): Collection
    {
        $moment = $now ?? now('Asia/Jakarta');

        return NotificationSchedule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($moment): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $moment);
            })
            ->orderBy('next_run_at')
            ->get();
    }

    /**
     * @return array{report: DailyReport, queued: int, skipped: int}
     */
    public function runSchedule(NotificationSchedule $schedule, ?Carbon $date = null, bool $queue = true): array
    {
        $period = $this->period($schedule, $date ?? now($schedule->timezone));
        $report = $this->snapshot($schedule, $period['report_date'], $period['start'], $period['end']);
        $result = $this->sendReport($report, $schedule, $queue);

        $next = $this->nextRun($schedule);
        $schedule->update([
            'last_run_at' => now('Asia/Jakarta'),
            'next_run_at' => $next,
        ]);

        return $result;
    }

    public function snapshot(NotificationSchedule $schedule, Carbon $reportDate, Carbon $start, Carbon $end): DailyReport
    {
        $idempotencyKey = hash('sha256', implode('|', [
            'daily_report',
            $schedule->id,
            $reportDate->toDateString(),
            $start->toDateString(),
            $end->toDateString(),
        ]));

        $existing = DailyReport::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof DailyReport) {
            return $existing;
        }

        $user = $this->systemReportUser();
        $filters = $this->reports->filters($user, [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'work_location_id' => $schedule->work_location_id,
        ]);
        $payload = $this->reports->report('daily', $user, $filters);

        return DB::transaction(fn (): DailyReport => DailyReport::query()->create([
            'schedule_id' => $schedule->id,
            'report_date' => $reportDate->toDateString(),
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => DailyReportStatus::GENERATED->value,
            'filters' => $this->serializableFilters($filters),
            'summary' => $payload['summary'],
            'rows' => $payload['rows'],
            'definitions' => $payload['definitions'],
            'generated_at' => now('Asia/Jakarta'),
            'generated_by' => $user->exists ? $user->id : null,
            'idempotency_key' => $idempotencyKey,
        ]));
    }

    /**
     * @return array{report: DailyReport, queued: int, skipped: int}
     */
    public function sendReport(DailyReport $report, NotificationSchedule $schedule, bool $queue = true): array
    {
        $template = $schedule->template ?: NotificationTemplate::query()
            ->where('key', 'daily_report')
            ->where('is_active', true)
            ->whereIn('channel_type', $schedule->channel_types ?: [NotificationChannelType::WHATSAPP->value, NotificationChannelType::TELEGRAM->value])
            ->orderByDesc('version')
            ->first();

        if (! $template instanceof NotificationTemplate) {
            $report->update(['status' => DailyReportStatus::FAILED->value]);

            return ['report' => $report, 'queued' => 0, 'skipped' => 0];
        }

        $queued = 0;
        $skipped = 0;
        foreach ($this->recipients($schedule) as $recipient) {
            if ($this->isQuietHour($recipient)) {
                $skipped++;

                continue;
            }

            $tokenPair = $this->createSecureToken($report, $recipient);
            $context = $this->context($report, $tokenPair['url']);
            $rendered = $this->renderer->render($template, $context);
            $idempotencyKey = hash('sha256', implode('|', ['daily_report_delivery', $report->id, $recipient->id, $template->id]));
            $log = $this->dispatcher->queueLog(
                $recipient->type(),
                $recipient->destination,
                $rendered['body'],
                $template,
                $recipient,
                $tokenPair['token'],
                null,
                ['report_id' => $report->id, 'secure_link' => $tokenPair['url']],
                $idempotencyKey,
                $rendered['subject'],
            );

            $queue ? SendNotificationJob::dispatch($log->id) : $this->dispatcher->send($log);
            $queued++;
        }

        $report->update(['status' => $queued > 0 ? DailyReportStatus::SENDING->value : DailyReportStatus::GENERATED->value]);

        return ['report' => $report->refresh(), 'queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * @return Collection<int, NotificationRecipient>
     */
    private function recipients(NotificationSchedule $schedule): Collection
    {
        $channels = $schedule->channel_types ?: [NotificationChannelType::WHATSAPP->value, NotificationChannelType::TELEGRAM->value];

        return NotificationRecipient::query()
            ->with(['user', 'workLocation'])
            ->where('is_active', true)
            ->where('report_type', $schedule->report_type)
            ->whereIn('channel_type', $channels)
            ->when($schedule->work_location_id, function ($query) use ($schedule): void {
                $query->where(function ($nested) use ($schedule): void {
                    $nested->whereNull('work_location_id')
                        ->orWhere('work_location_id', $schedule->work_location_id);
                });
            })
            ->get();
    }

    /**
     * @return array{token: SecureReportToken, plain: string, url: string}
     */
    private function createSecureToken(DailyReport $report, NotificationRecipient $recipient): array
    {
        $plain = Str::random(48);
        $token = SecureReportToken::query()->create([
            'daily_report_id' => $report->id,
            'token_hash' => hash('sha256', $plain),
            'recipient_destination' => $recipient->destination,
            'recipient_id' => $recipient->id,
            'user_id' => $recipient->user_id,
            'scope' => [
                'work_location_id' => $recipient->work_location_id,
                'role_name' => $recipient->role_name,
            ],
            'expires_at' => now('Asia/Jakarta')->addMinutes((int) config('notifications.secure_link_ttl_minutes', 1440)),
        ]);

        return [
            'token' => $token,
            'plain' => $plain,
            'url' => route('reports.daily.secure', ['token' => $plain]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function context(DailyReport $report, string $secureLink): array
    {
        $summary = $report->summaryData();

        return [
            'report_date' => $report->reportDate()->format('d/m/Y'),
            'revenue' => (string) ($summary['revenue'] ?? '0.00'),
            'gross_margin' => (string) ($summary['gross_margin'] ?? '0.00'),
            'margin_percent' => (string) ($summary['margin_percent'] ?? '0.00'),
            'stock_value' => (string) ($summary['stock_value'] ?? '0.00'),
            'critical_stock_count' => (string) ($summary['critical_stock_count'] ?? '0'),
            'receivable_outstanding' => (string) ($summary['receivable_outstanding'] ?? '0.00'),
            'overdue_receivable' => (string) ($summary['overdue_receivable'] ?? '0.00'),
            'cash_difference' => (string) ($summary['cash_difference'] ?? '0.00'),
            'attendance_late' => (string) ($summary['attendance_late'] ?? '0'),
            'anomaly_open' => (string) ($summary['anomaly_open'] ?? '0'),
            'pending_approval' => (string) ($summary['pending_approval'] ?? '0'),
            'secure_link' => $secureLink,
        ];
    }

    private function systemReportUser(): User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'owner_approver', 'owner_viewer']))
            ->first()
            ?? User::query()->where('is_active', true)->first()
            ?? new User;
    }

    /**
     * @return array{report_date: Carbon, start: Carbon, end: Carbon}
     */
    private function period(NotificationSchedule $schedule, Carbon $date): array
    {
        $base = match ($schedule->report_period) {
            'today' => $date->copy(),
            default => $date->copy()->subDay(),
        };

        return [
            'report_date' => $base->copy()->startOfDay(),
            'start' => $base->copy()->startOfDay(),
            'end' => $base->copy()->endOfDay(),
        ];
    }

    private function nextRun(NotificationSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone ?: 'Asia/Jakarta';
        $time = $schedule->run_time ?: (string) config('notifications.daily_report_time', '08:00');
        $next = Carbon::parse(now($timezone)->toDateString().' '.$time, $timezone);

        if ($next->lessThanOrEqualTo(now($timezone))) {
            $next->addDay();
        }

        return $next->timezone('Asia/Jakarta');
    }

    private function isQuietHour(NotificationRecipient $recipient): bool
    {
        if ($recipient->quiet_hours_start === null || $recipient->quiet_hours_end === null) {
            return false;
        }

        $now = now('Asia/Jakarta')->format('H:i:s');
        $start = Carbon::parse((string) $recipient->quiet_hours_start, 'Asia/Jakarta')->format('H:i:s');
        $end = Carbon::parse((string) $recipient->quiet_hours_end, 'Asia/Jakarta')->format('H:i:s');

        return $start <= $end
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function serializableFilters(array $filters): array
    {
        return collect($filters)
            ->map(fn (mixed $value): mixed => $value instanceof Carbon ? $value->toDateTimeString() : $value)
            ->all();
    }
}
