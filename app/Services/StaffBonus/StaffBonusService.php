<?php

namespace App\Services\StaffBonus;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\AttendanceVerificationStatus;
use App\Enums\PosSaleStatus;
use App\Enums\StaffBonusPeriodStatus;
use App\Enums\StaffBonusProgramStatus;
use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistStatus;
use App\Exceptions\ServiceException;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\B2bOrder;
use App\Models\CashShift;
use App\Models\EmployeeSchedule;
use App\Models\GoodsReceipt;
use App\Models\PosReturn;
use App\Models\PosSale;
use App\Models\ReturnDocument;
use App\Models\StaffBonusPeriod;
use App\Models\StaffBonusProgram;
use App\Models\StaffBonusResult;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Control\AuditLogService;
use App\Support\ApprovalAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffBonusService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly ApprovalWorkflowService $approvals,
    ) {}

    /** @param array<string, mixed> $data */
    public function createProgram(array $data, User $actor, ?Request $request = null): StaffBonusProgram
    {
        return DB::transaction(function () use ($data, $actor, $request): StaffBonusProgram {
            $nextVersion = ((int) StaffBonusProgram::query()->where('program_key', $data['program_key'])->max('version')) + 1;
            $program = StaffBonusProgram::query()->create([
                'program_key' => $data['program_key'], 'version' => $nextVersion, 'name' => $data['name'],
                'role_name' => $data['role_name'], 'work_location_id' => $data['work_location_id'] ?? null,
                'month' => $data['month'], 'year' => $data['year'],
                'maximum_bonus_amount' => $data['maximum_bonus_amount'], 'status' => StaffBonusProgramStatus::DRAFT,
                'created_by' => $actor->id,
            ]);
            $definitions = config('staff-bonuses.metrics', []);
            if (! is_array($definitions)) {
                throw ValidationException::withMessages(['metrics' => 'Konfigurasi KPI bonus tidak valid.']);
            }
            foreach (array_values($data['metrics']) as $index => $metric) {
                $definition = $definitions[$metric['metric_key']] ?? null;
                if (! is_array($definition)) {
                    throw ValidationException::withMessages(['metrics' => 'KPI bonus tidak dikenali.']);
                }
                $allowedRoles = $definition['roles'] ?? [];
                if (! is_array($allowedRoles) || ! in_array((string) $data['role_name'], $allowedRoles, true)) {
                    throw ValidationException::withMessages(['metrics' => 'KPI tidak sesuai dengan role program.']);
                }
                $program->metrics()->create([
                    'metric_key' => $metric['metric_key'], 'label' => (string) $definition['label'], 'scope' => (string) $definition['scope'],
                    'target_value' => $metric['target_value'], 'weight_percentage' => $metric['weight_percentage'], 'sort_order' => $index + 1,
                ]);
            }
            foreach ($data['user_ids'] ?? [] as $userId) {
                $override = [];
                foreach ($data['user_overrides'] ?? [] as $candidate) {
                    if (is_array($candidate) && (int) ($candidate['user_id'] ?? 0) === (int) $userId) {
                        $override = $candidate;
                        break;
                    }
                }
                $program->assignments()->create([
                    'user_id' => $userId,
                    'target_override' => $override['target_override'] ?? null,
                    'maximum_bonus_override' => $override['maximum_bonus_override'] ?? null,
                ]);
            }
            $this->audit->record('staff_bonus.program_created', 'staff_bonuses', $actor, $program, [], $program->toArray(), request: $request, location: $program->workLocation);

            return $program->fresh(['metrics', 'assignments.user', 'workLocation']);
        });
    }

    public function activate(StaffBonusProgram $program, User $actor, ?Request $request = null): StaffBonusPeriod
    {
        return DB::transaction(function () use ($program, $actor, $request): StaffBonusPeriod {
            $program = StaffBonusProgram::query()->with(['metrics', 'assignments.user.employee'])->lockForUpdate()->findOrFail($program->id);
            if ($program->status !== StaffBonusProgramStatus::DRAFT) {
                throw ServiceException::validation('Program bonus sudah aktif dan tidak dapat diaktifkan ulang.');
            }
            $start = CarbonImmutable::create($program->year, $program->month, 1, 0, 0, 0, config('staff-bonuses.timezone'));
            if (! $start->isFuture()) {
                throw ValidationException::withMessages(['period' => 'Program hanya dapat diaktifkan sebelum bulan target dimulai.']);
            }
            if (bccomp((string) $program->metrics->sum(fn ($metric): string => (string) $metric->weight_percentage), '100.0000', 4) !== 0) {
                throw ValidationException::withMessages(['metrics' => 'Total bobot KPI wajib tepat 100%.']);
            }
            if ($program->assignments->isEmpty()) {
                throw ValidationException::withMessages(['user_ids' => 'Pilih minimal satu akun penerima bonus.']);
            }
            $expectedLocationType = match ($program->role_name) {
                'staff_gudang', 'picker_packer' => 'warehouse',
                'staf_toko', 'kasir' => 'branch',
                default => null,
            };
            if ($expectedLocationType === null && $program->work_location_id !== null) {
                throw ValidationException::withMessages(['work_location_id' => 'Program Sales harus menggunakan lingkup global.']);
            }
            if ($expectedLocationType !== null && ($program->workLocation === null || $program->workLocation->type !== $expectedLocationType)) {
                throw ValidationException::withMessages(['work_location_id' => 'Jenis lokasi tidak sesuai dengan role program.']);
            }
            $userIds = $program->assignments->pluck('user_id');
            $duplicate = StaffBonusResult::query()->whereIn('user_id', $userIds)
                ->whereHas('period', fn ($query) => $query->where('month', $program->month)->where('year', $program->year))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['user_ids' => 'Salah satu akun sudah memiliki program bonus pada periode tersebut.']);
            }
            foreach ($program->assignments as $assignment) {
                $employee = $assignment->user->employee;
                if ($employee === null || ! $employee->is_active || ! $assignment->user->hasRole($program->role_name)) {
                    throw ValidationException::withMessages(['user_ids' => 'Semua penerima harus merupakan karyawan aktif dengan role yang sesuai.']);
                }
                if ($program->work_location_id !== null && (int) $employee->work_location_id !== (int) $program->work_location_id) {
                    throw ValidationException::withMessages(['user_ids' => 'Lokasi utama karyawan harus sama dengan lokasi program.']);
                }
            }

            $snapshot = $this->programSnapshot($program);
            $program->forceFill(['status' => StaffBonusProgramStatus::ACTIVE, 'activated_by' => $actor->id, 'activated_at' => now(), 'configuration_snapshot' => $snapshot])->save();
            $period = StaffBonusPeriod::query()->create([
                'staff_bonus_program_id' => $program->id, 'work_location_id' => $program->work_location_id,
                'month' => $program->month, 'year' => $program->year, 'status' => StaffBonusPeriodStatus::ACTIVE,
                'program_snapshot' => $snapshot,
            ]);
            foreach ($program->assignments as $assignment) {
                $employee = $assignment->user->employee;
                $period->results()->create([
                    'user_id' => $assignment->user_id, 'work_location_id' => $program->work_location_id,
                    'role_name' => $program->role_name,
                    'maximum_bonus_amount' => $assignment->maximum_bonus_override ?? $program->maximum_bonus_amount,
                    'employee_snapshot' => ['name' => $assignment->user->name, 'employee_no' => $employee?->employee_no, 'work_location_id' => $employee?->work_location_id, 'role' => $program->role_name],
                ]);
            }
            $this->calculate($period);
            $this->audit->record('staff_bonus.program_activated', 'staff_bonuses', $actor, $program, [], $snapshot, request: $request, location: $program->workLocation);

            return $period->fresh(['results.metrics', 'program.metrics']);
        });
    }

    public function calculate(StaffBonusPeriod $period): StaffBonusPeriod
    {
        return DB::transaction(function () use ($period): StaffBonusPeriod {
            $period = StaffBonusPeriod::query()->with(['program.metrics', 'results'])->lockForUpdate()->findOrFail($period->id);
            if (in_array($period->status, [StaffBonusPeriodStatus::APPROVED, StaffBonusPeriodStatus::CLOSED], true)) {
                return $period;
            }
            [$start, $end] = $this->bounds($period->month, $period->year);
            foreach ($period->results as $result) {
                $weightedTotal = '0.00';
                foreach ($period->program->metrics as $metric) {
                    $actual = $this->actual($metric->metric_key, $result, $start, $end);
                    if (bccomp($actual, '0', 4) < 0) {
                        $actual = '0.0000';
                    }
                    $target = (string) $metric->target_value;
                    $primaryMetric = match ($result->role_name) {
                        'staff_gudang', 'picker_packer' => 'warehouse_tasks',
                        'staf_toko', 'kasir' => 'pos_net_sales',
                        'sales' => 'b2b_net_sales',
                        default => null,
                    };
                    if ($metric->metric_key === $primaryMetric) {
                        $override = $period->program->assignments()->where('user_id', $result->user_id)->value('target_override');
                        $target = $override !== null ? (string) $override : $target;
                    }
                    $score = bccomp($target, '0', 4) > 0 ? bcmul(bcdiv($actual, $target, 8), '100', 2) : '0.00';
                    if (bccomp($score, '100.00', 2) > 0) {
                        $score = '100.00';
                    }
                    $weighted = bcmul($score, bcdiv((string) $metric->weight_percentage, '100', 8), 2);
                    $weightedTotal = bcadd($weightedTotal, $weighted, 2);
                    $result->metrics()->updateOrCreate(['metric_key' => $metric->metric_key], [
                        'label' => $metric->label, 'scope' => $metric->scope, 'target_value' => $target,
                        'actual_value' => $actual, 'score' => $score, 'weight_percentage' => $metric->weight_percentage,
                        'weighted_score' => $weighted, 'source_snapshot' => ['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
                    ]);
                }
                $calculatedBonus = bcmul((string) $result->maximum_bonus_amount, bcdiv($weightedTotal, '100', 8), 2);
                $adjustment = (string) DB::table('staff_bonus_adjustments')->where('target_result_id', $result->id)->sum('amount');
                $bonus = bcadd($calculatedBonus, $adjustment, 2);
                if (bccomp($bonus, '0', 2) < 0) {
                    $bonus = '0.00';
                }
                $result->forceFill(['final_score' => $weightedTotal, 'calculated_bonus_amount' => $calculatedBonus, 'adjustment_amount' => $adjustment, 'bonus_amount' => $bonus, 'calculated_at' => now()])->save();
            }
            $period->forceFill(['total_bonus_amount' => $period->results()->sum('bonus_amount'), 'last_calculated_at' => now()])->save();

            return $period->fresh(['results.metrics', 'program.metrics']);
        });
    }

    public function submit(StaffBonusPeriod $period, User $actor, ?Request $request = null): StaffBonusPeriod
    {
        [$start] = $this->bounds($period->month, $period->year);
        if (! $start->addMonth()->isPast()) {
            throw ValidationException::withMessages(['period' => 'Bonus hanya dapat diajukan setelah periode berakhir.']);
        }
        if (Attendance::query()->whereIn('user_id', $period->results()->pluck('user_id'))->whereBetween('attendance_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->where('verification_status', AttendanceVerificationStatus::PENDING->value)->exists()) {
            throw ValidationException::withMessages(['attendance' => 'Masih ada absensi yang menunggu verifikasi.']);
        }
        $period = $this->calculate($period);
        $requiredRole = in_array($period->program->role_name, ['staf_toko', 'kasir'], true)
            ? ApprovalAuthority::STORE_HEAD
            : ApprovalAuthority::WAREHOUSE_HEAD;
        $approval = $this->approvals->create($period, 'staff_bonus_period', 'staff_bonuses', $actor, (string) $period->total_bonus_amount, 'Persetujuan bonus staf '.$period->month.'/'.$period->year, after: ['total_bonus_amount' => $period->total_bonus_amount], location: $period->workLocation, requiredPermission: 'staff_bonuses.approve', requiredRole: $requiredRole, handlerKey: 'staff_bonus.period');
        $period->forceFill(['status' => StaffBonusPeriodStatus::PENDING_APPROVAL, 'submitted_at' => now(), 'submitted_by' => $actor->id, 'approval_request_id' => $approval->id])->save();
        $this->audit->record('staff_bonus.period_submitted', 'staff_bonuses', $actor, $period, [], ['total_bonus_amount' => $period->total_bonus_amount], request: $request, location: $period->workLocation);

        return $period->fresh();
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function bounds(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('staff-bonuses.timezone'));

        return [$start, $start->endOfMonth()];
    }

    private function actual(string $key, StaffBonusResult $result, CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($key) {
            'warehouse_tasks' => $this->warehouseTasks((int) $result->work_location_id, $start, $end),
            'warehouse_accuracy' => $this->warehouseAccuracy((int) $result->work_location_id, $start, $end),
            'pos_net_sales' => $this->posNetSales((int) $result->work_location_id, $start, $end),
            'cash_accuracy' => $this->cashAccuracy((int) $result->work_location_id, $start, $end),
            'b2b_net_sales' => $this->b2bNetSales((int) $result->user_id, $start, $end),
            'attendance_rate' => $this->attendanceRate((int) $result->user_id, $start, $end),
            'checklist_on_time' => $this->checklistRate((int) $result->user_id, $result->work_location_id, $start, $end),
            default => '0.0000',
        };
    }

    private function warehouseTasks(int $locationId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $receipts = GoodsReceipt::query()->where('destination_work_location_id', $locationId)->whereNotNull('posted_at')->whereBetween('posted_at', [$start, $end])->count();
        $transfers = StockTransfer::query()->where(fn ($q) => $q->where('source_work_location_id', $locationId)->orWhere('destination_work_location_id', $locationId))->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end])->count();
        $opnames = StockOpname::query()->where('work_location_id', $locationId)->whereNotNull('completed_at')->whereBetween('completed_at', [$start, $end])->count();

        return number_format($receipts + $transfers + $opnames, 4, '.', '');
    }

    private function warehouseAccuracy(int $locationId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $rows = DB::table('stock_transfer_items')->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where(fn ($q) => $q->where('stock_transfers.source_work_location_id', $locationId)->orWhere('stock_transfers.destination_work_location_id', $locationId))
            ->whereBetween('stock_transfers.completed_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(quantity_shipped),0) as total, COALESCE(SUM(ABS(quantity_discrepancy)),0) as discrepancy')->first();
        $total = (string) ($rows->total ?? '0');
        if (bccomp($total, '0', 4) <= 0) {
            return '0.0000';
        }
        $valid = bcsub($total, (string) ($rows->discrepancy ?? '0'), 4);
        if (bccomp($valid, '0', 4) < 0) {
            $valid = '0';
        }

        return bcmul(bcdiv($valid, $total, 8), '100', 4);
    }

    private function posNetSales(int $locationId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $sales = PosSale::query()->where('work_location_id', $locationId)->whereIn('status', [PosSaleStatus::COMPLETED->value, PosSaleStatus::RETURNED->value])->whereBetween('completed_at', [$start, $end])->sum('grand_total_amount');
        $returns = PosReturn::query()->where('work_location_id', $locationId)->where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('refund_amount');

        return bcadd(bcsub((string) $sales, (string) $returns, 2), '0', 4);
    }

    private function cashAccuracy(int $locationId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $shifts = CashShift::query()->where('work_location_id', $locationId)->whereNotNull('approved_at')->whereBetween('approved_at', [$start, $end])->get(['difference_amount', 'discrepancy_threshold_amount']);
        if ($shifts->isEmpty()) {
            return '0.0000';
        }
        $clean = $shifts->filter(fn ($shift): bool => bccomp(ltrim((string) $shift->difference_amount, '-'), (string) $shift->discrepancy_threshold_amount, 2) <= 0)->count();

        return bcmul(bcdiv((string) $clean, (string) $shifts->count(), 8), '100', 4);
    }

    private function b2bNetSales(int $userId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $orders = B2bOrder::query()->where('sales_user_id', $userId);
        $ids = (clone $orders)->pluck('id');
        $gross = (string) (clone $orders)->where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('grand_total_amount');
        $returns = ReturnDocument::query()->where('reference_type', 'b2b_order')->whereIn('reference_id', $ids)->where('status', 'settled')->whereBetween('settled_at', [$start, $end])->sum('total_value');

        return bcadd(bcsub($gross, (string) $returns, 2), '0', 4);
    }

    private function attendanceRate(int $userId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $employeeId = User::query()->find($userId)?->employee?->id;
        if ($employeeId === null) {
            return '0.0000';
        }
        $schedules = EmployeeSchedule::query()->where('employee_id', $employeeId)->where('status', 'scheduled')->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])->get();
        $excused = AttendanceRequest::query()->where('employee_id', $employeeId)->where('status', AttendanceRequestStatus::APPROVED->value)
            ->whereIn('type', [AttendanceRequestType::PERMISSION->value, AttendanceRequestType::SICK->value, AttendanceRequestType::LEAVE->value])
            ->where('start_at', '<=', $end)->where('end_at', '>=', $start)->get();
        $requiredDates = $schedules->reject(fn ($schedule): bool => $excused->contains(function ($leave) use ($schedule): bool {
            $leaveStart = CarbonImmutable::parse((string) $leave->start_at);
            $leaveEnd = CarbonImmutable::parse((string) $leave->end_at);

            return CarbonImmutable::parse((string) $schedule->scheduled_date)->between($leaveStart->startOfDay(), $leaveEnd->endOfDay());
        }))->pluck('scheduled_date')->map->toDateString()->unique();
        if ($requiredDates->isEmpty()) {
            return '0.0000';
        }
        $present = Attendance::query()->where('user_id', $userId)->whereIn('attendance_date', $requiredDates)->where('verification_status', AttendanceVerificationStatus::APPROVED->value)->distinct()->count('attendance_date');

        return bcmul(bcdiv((string) $present, (string) $requiredDates->count(), 8), '100', 4);
    }

    private function checklistRate(int $userId, ?int $locationId, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $query = WorkChecklist::query()->where('user_id', $userId)->where('frequency', WorkChecklistFrequency::DAILY->value)->whereBetween('period_start', [$start->toDateString(), $end->toDateString()]);
        $locationId === null ? $query->whereNull('work_location_id') : $query->where('work_location_id', $locationId);
        $runs = $query->get();
        if ($runs->isEmpty()) {
            return '0.0000';
        }
        $onTime = $runs->filter(fn ($run): bool => $run->status === WorkChecklistStatus::COMPLETED && $run->first_completed_at !== null && $run->first_completed_at->lte($run->due_at))->count();

        return bcmul(bcdiv((string) $onTime, (string) $runs->count(), 8), '100', 4);
    }

    /** @return array<string, mixed> */
    private function programSnapshot(StaffBonusProgram $program): array
    {
        return ['name' => $program->name, 'program_key' => $program->program_key, 'version' => $program->version, 'role_name' => $program->role_name, 'work_location_id' => $program->work_location_id, 'month' => $program->month, 'year' => $program->year, 'maximum_bonus_amount' => $program->maximum_bonus_amount, 'metrics' => $program->metrics->map->only(['metric_key', 'label', 'scope', 'target_value', 'weight_percentage'])->values()->all()];
    }
}
