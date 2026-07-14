<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view'), 403);

        return view('attendance.employees.index', [
            'employees' => Employee::query()
                ->with(['user', 'workLocation'])
                ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$request->query('q').'%')->orWhere('employee_no', 'like', '%'.$request->query('q').'%')))
                ->when($request->filled('work_location_id'), fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'statuses' => EmployeeStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('attendance.update'), 403);

        return view('attendance.employees.form', $this->formData($request));
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        abort_unless($request->user()->canAccessWorkLocation((int) $request->validated('work_location_id')), 403);
        Employee::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('attendance.employees.index')->with('notification', ['type' => 'success', 'message' => 'Karyawan berhasil dibuat.']);
    }

    public function edit(Request $request, Employee $employee): View
    {
        abort_unless($request->user()->can('attendance.update') && $request->user()->canAccessWorkLocation((int) $employee->work_location_id), 403);

        return view('attendance.employees.form', $this->formData($request, $employee));
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->canAccessWorkLocation((int) $request->validated('work_location_id')), 403);
        $history = $employee->placement_history ?? [];
        if ((int) $employee->work_location_id !== (int) $request->validated('work_location_id')) {
            $history[] = ['from' => $employee->work_location_id, 'to' => $request->validated('work_location_id'), 'changed_at' => now()->toDateTimeString(), 'actor_user_id' => $request->user()->id];
        }
        $employee->forceFill([...$request->validated(), 'is_active' => $request->boolean('is_active'), 'placement_history' => $history])->save();

        return redirect()->route('attendance.employees.index')->with('notification', ['type' => 'success', 'message' => 'Karyawan berhasil diperbarui.']);
    }

    public function deactivate(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.update') && $request->user()->canAccessWorkLocation((int) $employee->work_location_id), 403);
        $employee->forceFill(['is_active' => false, 'status' => EmployeeStatus::INACTIVE])->save();

        return back()->with('notification', ['type' => 'success', 'message' => 'Karyawan berhasil dinonaktifkan.']);
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?Employee $employee = null): array
    {
        return [
            'employee' => $employee,
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->limit(200)->get(),
            'statuses' => EmployeeStatus::cases(),
        ];
    }
}
