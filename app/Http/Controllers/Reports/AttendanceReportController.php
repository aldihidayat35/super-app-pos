<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceReportController extends Controller
{
    public function attendance(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view') || $request->user()->can('reports.view'), 403);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $base = Attendance::query()
            ->with(['employee', 'workLocation'])
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->whereBetween('attendance_date', [$from, $to])
            ->when($request->filled('work_location_id'), fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')));

        return view('reports.attendance', [
            'attendances' => (clone $base)->latest('attendance_date')->paginate(20)->withQueryString(),
            'summary' => (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'employees' => Employee::query()->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'filters' => compact('from', 'to'),
        ]);
    }

    public function productivity(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view') || $request->user()->can('reports.view'), 403);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $shifts = CashShift::query()
            ->with(['cashier.employee', 'branch', 'attendance'])
            ->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())
            ->whereDate('opened_at', '>=', $from)
            ->whereDate('opened_at', '<=', $to)
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        $salesByShift = PosSale::query()
            ->select('cash_shift_id', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(grand_total_amount) as omzet'), DB::raw('SUM(discount_amount) as discount_total'))
            ->whereIn('cash_shift_id', $shifts->pluck('id')->all())
            ->groupBy('cash_shift_id')
            ->get()
            ->keyBy('cash_shift_id');

        return view('reports.shift-productivity', [
            'shifts' => $shifts,
            'salesByShift' => $salesByShift,
            'filters' => compact('from', 'to'),
        ]);
    }
}
