<?php

namespace App\Http\Controllers\Attendance;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SupervisorCheckOutRequest;
use App\Http\Requests\Attendance\VerifyAttendanceRequest;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AttendanceRecordController extends Controller
{
    public function approve(VerifyAttendanceRequest $request, Attendance $attendance, AttendanceService $service): RedirectResponse
    {
        return $this->verify($request, $attendance, $service, true);
    }

    public function reject(VerifyAttendanceRequest $request, Attendance $attendance, AttendanceService $service): RedirectResponse
    {
        if (! filled($request->validated('note'))) {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->verify($request, $attendance, $service, false);
    }

    public function supervisorCheckOut(SupervisorCheckOutRequest $request, Attendance $attendance, AttendanceService $service): RedirectResponse
    {
        try {
            $service->supervisorCheckOut($attendance, $request->user(), $request->validated('reason'), $request);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Jam pulang darurat berhasil dicatat.']);
    }

    private function verify(VerifyAttendanceRequest $request, Attendance $attendance, AttendanceService $service, bool $approved): RedirectResponse
    {
        try {
            $service->verify($attendance, $request->user(), $approved, $request->validated('note'), $request);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['attendance' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => $approved ? 'Absensi berhasil disetujui.' : 'Absensi berhasil ditolak.']);
    }
}
