<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceRequestRequest;
use App\Models\AttendanceRequest;
use App\Services\Attendance\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.check') || $request->user()->can('attendance.approve'), 403);

        return view('attendance.requests.index', [
            'requests' => AttendanceRequest::query()
                ->with(['employee', 'workLocation'])
                ->when(! $request->user()->can('attendance.approve'), fn ($query) => $query->where('user_id', $request->user()->id))
                ->when($request->user()->can('attendance.approve'), fn ($query) => $query->whereIn('work_location_id', $request->user()->permittedWorkLocationIds()))
                ->latest('id')
                ->paginate(15),
            'types' => AttendanceRequestType::cases(),
            'statuses' => AttendanceRequestStatus::cases(),
        ]);
    }

    public function store(StoreAttendanceRequestRequest $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')?->store('attendance-requests');
        }
        try {
            $service->submitRequest($request->user(), $data);
        } catch (ServiceException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return back()->with('notification', ['type' => 'success', 'message' => 'Pengajuan kehadiran berhasil dikirim.']);
    }

    public function approve(Request $request, AttendanceRequest $attendanceRequest, AttendanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.approve'), 403);
        $service->approveRequest($attendanceRequest, $request->user(), true, $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Pengajuan berhasil disetujui.']);
    }

    public function reject(Request $request, AttendanceRequest $attendanceRequest, AttendanceService $service): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.approve'), 403);
        $service->approveRequest($attendanceRequest, $request->user(), false, $request->input('approval_note'));

        return back()->with('notification', ['type' => 'success', 'message' => 'Pengajuan berhasil ditolak.']);
    }
}
