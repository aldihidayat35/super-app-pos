<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\EmployeeScheduleStatus;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreSchedulePatternRequest;
use App\Http\Requests\Attendance\StoreScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeSchedulePattern;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view'), 403);

        return view('attendance.schedules.index', [
            'schedules' => EmployeeSchedule::query()
                ->with(['employee', 'workShift', 'workLocation'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('scheduled_date', '>=', $request->query('date_from')))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('scheduled_date', '<=', $request->query('date_to')))
                ->orderBy('scheduled_date')
                ->paginate(20)
                ->withQueryString(),
            'employees' => Employee::query()->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->where('is_active', true)->orderBy('name')->get(),
            'shifts' => WorkShift::query()->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('work_location_id')->orWhereIn('work_location_id', $request->user()->permittedWorkLocationIds()))
                ->orderBy('name')->get(),
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'statuses' => EmployeeScheduleStatus::cases(),
            'patterns' => EmployeeSchedulePattern::query()->with(['employee', 'workLocation', 'days.workShift'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->where('is_active', true)->latest('effective_from')->get()->unique('employee_id')->values(),
        ]);
    }

    public function store(StoreScheduleRequest $request, AttendanceService $service): RedirectResponse
    {
        try {
            $service->createSchedule($request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['schedule' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Jadwal shift berhasil disimpan.']);
    }

    public function storePattern(StoreSchedulePatternRequest $request, AttendanceService $service): RedirectResponse
    {
        try {
            $employee = Employee::query()->findOrFail($request->integer('employee_id'));
            $service->saveWeeklyPattern($employee, $request->validated(), $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['pattern' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Pola jadwal mingguan berhasil disimpan.']);
    }
}
