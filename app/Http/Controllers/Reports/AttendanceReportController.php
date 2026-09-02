<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\PosSale;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->when(in_array($request->query('status'), array_column(AttendanceStatus::cases(), 'value'), true), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('work_location_id'), fn ($query) => $query->where('work_location_id', $request->integer('work_location_id')))
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')));

        $statusSummary = (clone $base)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $dailyRows = (clone $base)
            ->select('attendance_date', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('attendance_date', 'status')
            ->orderBy('attendance_date')
            ->get()
            ->groupBy(fn ($row): string => (string) $row->attendance_date)
            ->map(function (Collection $items, string $date): array {
                $byStatus = $items->pluck('total', 'status');

                return [
                    'date' => $date,
                    'on_time' => (int) ($byStatus[AttendanceStatus::PRESENT->value] ?? 0),
                    'late' => (int) ($byStatus[AttendanceStatus::LATE->value] ?? 0),
                    'absent' => collect([AttendanceStatus::PERMISSION, AttendanceStatus::SICK, AttendanceStatus::ALPHA, AttendanceStatus::LEAVE])->sum(fn (AttendanceStatus $status): int => (int) ($byStatus[$status->value] ?? 0)),
                ];
            })
            ->values();
        $locationSummary = (clone $base)
            ->join('work_locations', 'work_locations.id', '=', 'attendances.work_location_id')
            ->selectRaw('work_locations.name as location, COUNT(attendances.id) as total, SUM(CASE WHEN attendances.status = ? THEN 1 ELSE 0 END) as late_count, COALESCE(SUM(attendances.late_minutes),0) as late_minutes', [AttendanceStatus::LATE->value])
            ->groupBy('work_locations.id', 'work_locations.name')
            ->orderByDesc('late_minutes')
            ->limit(8)
            ->get();
        $totalAttendance = (int) $statusSummary->sum();
        $presentAttendance = (int) ($statusSummary[AttendanceStatus::PRESENT->value] ?? 0) + (int) ($statusSummary[AttendanceStatus::LATE->value] ?? 0);

        return view('reports.attendance', [
            'attendances' => (clone $base)->latest('attendance_date')->paginate(20)->withQueryString(),
            'summary' => $statusSummary,
            'metrics' => [
                'total' => $totalAttendance,
                'attendance_rate' => $totalAttendance === 0 ? 0 : round(($presentAttendance / $totalAttendance) * 100, 2),
                'late_minutes' => (int) (clone $base)->sum('late_minutes'),
                'worked_hours' => round(((int) (clone $base)->sum('worked_minutes')) / 60, 1),
            ],
            'dailyRows' => $dailyRows,
            'locationSummary' => $locationSummary,
            'locations' => WorkLocation::query()->whereIn('id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'employees' => Employee::query()->whereIn('work_location_id', $request->user()->permittedWorkLocationIds())->orderBy('name')->get(),
            'statuses' => AttendanceStatus::cases(),
            'filters' => ['from' => $from, 'to' => $to, 'status' => $request->query('status')],
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
