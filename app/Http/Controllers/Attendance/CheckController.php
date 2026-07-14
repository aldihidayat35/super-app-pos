<?php

namespace App\Http\Controllers\Attendance;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CheckAttendanceRequest;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckController extends Controller
{
    public function show(Request $request, AttendanceService $service): View
    {
        abort_unless($request->user()->can('attendance.check'), 403);
        $employee = null;
        $schedule = null;
        $openAttendance = null;

        try {
            $employee = $service->employeeForUser($request->user());
            $schedule = $service->activeScheduleFor($employee);
            $openAttendance = Attendance::query()->where('employee_id', $employee->id)->whereNull('check_out_at')->latest('check_in_at')->first();
        } catch (ServiceException) {
            //
        }

        return view('attendance.check.show', compact('employee', 'schedule', 'openAttendance'));
    }

    public function checkIn(CheckAttendanceRequest $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('attendance-proofs');
        }
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
