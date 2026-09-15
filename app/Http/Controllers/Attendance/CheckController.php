<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CheckAttendanceRequest;
use App\Models\Attendance;
use App\Models\CashShift;
use App\Models\EmployeeSchedule;
use App\Models\WorkChecklist;
use App\Services\Attendance\AttendanceService;
use App\Services\WorkChecklist\WorkChecklistService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckController extends Controller
{
    public function show(Request $request, AttendanceService $service, WorkChecklistService $checklistService): View
    {
        abort_unless($request->user()->can('attendance.check') || $request->user()->can('attendance.view'), 403);
        $employee = null;
        $schedule = null;
        $openAttendance = null;

        try {
            $employee = $service->employeeForUser($request->user());
            $schedule = $service->activeScheduleFor($employee);
            $openAttendance = Attendance::query()->where('employee_id', $employee->id)->whereNull('check_out_at')->latest('check_in_at')->first();
            $checklistService->generateCurrentFor($request->user());
        } catch (ServiceException) {
            //
        }

        $locationId = $openAttendance instanceof Attendance
            ? $openAttendance->work_location_id
            : ($schedule instanceof EmployeeSchedule ? $schedule->work_location_id : null);
        $attendanceDate = $openAttendance instanceof Attendance
            ? now()->parse((string) $openAttendance->getRawOriginal('attendance_date'))->toDateString()
            : ($schedule instanceof EmployeeSchedule
                ? now()->parse((string) $schedule->getRawOriginal('scheduled_date'))->toDateString()
                : now()->toDateString());
        $dailyChecklist = $locationId ? WorkChecklist::query()->with('items')
            ->where('user_id', $request->user()->id)->where('work_location_id', $locationId)
            ->where('frequency', 'daily')->whereDate('period_start', $attendanceDate)->first() : null;
        $recentAttendances = $employee ? Attendance::query()->with(['workLocation', 'verifier'])->where('employee_id', $employee->id)->latest('attendance_date')->limit(7)->get() : collect();
        $teamAttendances = collect();
        $pendingAttendances = collect();
        $teamSummary = ['scheduled' => 0, 'checked_in' => 0, 'late' => 0, 'not_checked_in' => 0, 'checked_out' => 0, 'pending_verification' => 0];
        $pendingChecklistSummaries = collect();
        if ($request->user()->can('attendance.view')) {
            $base = Attendance::query()->with(['employee.user.roles', 'workLocation', 'verifier'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds());
            $teamAttendances = (clone $base)->whereDate('attendance_date', now()->toDateString())->latest('check_in_at')->get();
            $pendingAttendances = (clone $base)->where('verification_status', 'pending')->latest('check_out_at')->limit(50)->get();
            $scheduled = EmployeeSchedule::query()
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->whereDate('scheduled_date', now()->toDateString())
                ->where('status', 'scheduled')
                ->count();
            $teamSummary = [
                'scheduled' => $scheduled,
                'checked_in' => $teamAttendances->count(),
                'late' => $teamAttendances->where('status', AttendanceStatus::LATE)->count(),
                'not_checked_in' => max(0, $scheduled - $teamAttendances->pluck('employee_schedule_id')->filter()->unique()->count()),
                'checked_out' => $teamAttendances->whereNotNull('check_out_at')->count(),
                'pending_verification' => $pendingAttendances->count(),
            ];
            if ($pendingAttendances->isNotEmpty()) {
                $pendingChecklistSummaries = WorkChecklist::query()->with('items')
                    ->whereIn('user_id', $pendingAttendances->pluck('user_id')->unique())
                    ->whereIn('work_location_id', $pendingAttendances->pluck('work_location_id')->unique())
                    ->where('frequency', 'daily')
                    ->whereBetween('period_start', [$pendingAttendances->min('attendance_date'), $pendingAttendances->max('attendance_date')])
                    ->get()
                    ->keyBy(fn (WorkChecklist $checklist): string => $checklist->user_id.'|'.$checklist->work_location_id.'|'.$checklist->period_start->toDateString());
            }
        }

        return view('attendance.check.show', [
            'employee' => $employee, 'schedule' => $schedule, 'openAttendance' => $openAttendance,
            'dailyChecklist' => $dailyChecklist, 'recentAttendances' => $recentAttendances,
            'teamAttendances' => $teamAttendances, 'pendingAttendances' => $pendingAttendances,
            'teamSummary' => $teamSummary, 'pendingChecklistSummaries' => $pendingChecklistSummaries,
            'hasOpenCashShift' => CashShift::query()->where('cashier_user_id', $request->user()->id)->whereIn('status', ['open', 'rejected'])->exists(),
        ]);
    }

    public function checkIn(CheckAttendanceRequest $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validated();
        try {
            $service->checkIn($request->user(), $data);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Check-in berhasil dicatat.']);
    }

    public function checkOut(CheckAttendanceRequest $request, AttendanceService $service): RedirectResponse
    {
        try {
            $service->checkOut($request->user(), $request->validated());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Check-out berhasil dicatat.']);
    }
}
