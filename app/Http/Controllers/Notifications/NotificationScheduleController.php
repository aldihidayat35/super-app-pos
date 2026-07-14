<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StoreNotificationScheduleRequest;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\WorkLocation;
use App\Services\Notifications\DailyReportNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationScheduleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return view('notifications.schedules.index', [
            'schedules' => NotificationSchedule::query()->with(['template', 'workLocation'])->latest('id')->paginate(20)->withQueryString(),
            'templates' => NotificationTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreNotificationScheduleRequest $request): RedirectResponse
    {
        NotificationSchedule::query()->create($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Jadwal notifikasi berhasil disimpan.']);
    }

    public function update(StoreNotificationScheduleRequest $request, NotificationSchedule $schedule): RedirectResponse
    {
        $schedule->update($this->payload($request));

        return back()->with('notification', ['type' => 'success', 'message' => 'Jadwal notifikasi berhasil diperbarui.']);
    }

    public function run(Request $request, NotificationSchedule $schedule, DailyReportNotificationService $service): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send'), 403);
        $result = $service->runSchedule($schedule, queue: false);

        return back()->with('notification', ['type' => 'success', 'message' => "Jadwal diproses: report #{$result['report']->id}, queued {$result['queued']}, skipped {$result['skipped']}."]);
    }

    /** @return array<string, mixed> */
    private function payload(StoreNotificationScheduleRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'schedule_key' => $validated['schedule_key'],
            'frequency' => $validated['frequency'],
            'run_time' => $validated['run_time'],
            'timezone' => $validated['timezone'],
            'report_type' => $validated['report_type'],
            'report_period' => $validated['report_period'],
            'template_id' => $validated['template_id'] ?? null,
            'channel_types' => $validated['channel_types'],
            'work_location_id' => $validated['work_location_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
