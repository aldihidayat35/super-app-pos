<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreWorkShiftRequest;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view'), 403);

        return view('attendance.work-shifts.index', [
            'shifts' => WorkShift::query()
                ->with('workLocation')
                ->where(fn ($query) => $query->whereNull('work_location_id')->orWhereIn('work_location_id', $request->user()->permittedWorkLocationIds()))
                ->latest('id')
                ->paginate(15),
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('attendance.update'), 403);

        return view('attendance.work-shifts.form', ['shift' => null, 'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get()]);
    }

    public function store(StoreWorkShiftRequest $request): RedirectResponse
    {
        $data = $request->validated();
        WorkShift::query()->create([...$data, 'is_cross_midnight' => $request->boolean('is_cross_midnight'), 'is_active' => $request->boolean('is_active', true), 'break_minutes' => $data['break_minutes'] ?? 0]);

        return redirect()->route('attendance.work-shifts.index')->with('notification', ['type' => 'success', 'message' => 'Master shift berhasil dibuat.']);
    }

    public function edit(Request $request, WorkShift $workShift): View
    {
        abort_unless($request->user()->can('attendance.update'), 403);

        return view('attendance.work-shifts.form', ['shift' => $workShift, 'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get()]);
    }

    public function update(StoreWorkShiftRequest $request, WorkShift $workShift): RedirectResponse
    {
        $data = $request->validated();
        $workShift->forceFill([...$data, 'is_cross_midnight' => $request->boolean('is_cross_midnight'), 'is_active' => $request->boolean('is_active'), 'break_minutes' => $data['break_minutes'] ?? 0])->save();

        return redirect()->route('attendance.work-shifts.index')->with('notification', ['type' => 'success', 'message' => 'Master shift berhasil diperbarui.']);
    }
}
