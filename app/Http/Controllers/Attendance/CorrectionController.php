<?php

namespace App\Http\Controllers\Attendance;

use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CorrectionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.update') || $request->user()->can('attendance.approve'), 403);

        return view('attendance.corrections.index', [
            'attendances' => Attendance::query()->with(['employee', 'workLocation'])->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->latest('id')->limit(50)->get(),
            'corrections' => AttendanceCorrection::query()->with(['employee', 'attendance'])->whereHas('attendance', fn ($query) => $query->whereIn('work_location_id', $request->user()->permittedWorkLocationIds()))->latest('id')->paginate(15),
        ]);
    }

    public function store(StoreCorrectionRequest $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('attendance-corrections');
        }
        $attendance = Attendance::query()->findOrFail($request->integer('attendance_id'));
        try {
            $service->submitCorrection($attendance, $data, $request->user());
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['correction' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Koreksi absensi berhasil diajukan.']);
    }

    public function approve(Request $request, AttendanceCorrection $correction, AttendanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.approve'), 403);
        $service->approveCorrection($correction, $request->user(), true, $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Koreksi absensi berhasil disetujui.']);
    }

    public function reject(Request $request, AttendanceCorrection $correction, AttendanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.approve'), 403);
        $service->approveCorrection($correction, $request->user(), false, $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Koreksi absensi berhasil ditolak.']);
    }
}
