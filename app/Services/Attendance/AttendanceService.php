<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\AttendanceStatus;
use App\Enums\AttendanceVerificationStatus;
use App\Enums\CashShiftStatus;
use App\Enums\EmployeeScheduleStatus;
use App\Enums\WorkChecklistFrequency;
use App\Enums\WorkChecklistStatus;
use App\Exceptions\ServiceException;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRequest;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\EmployeeSchedulePattern;
use App\Models\User;
use App\Models\WorkChecklist;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\Control\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function employeeForUser(User $user): Employee
    {
        $employee = Employee::query()->where('user_id', $user->id)->where('is_active', true)->first();
        if (! $employee instanceof Employee) {
            throw ServiceException::validation('Akun ini belum terhubung ke master karyawan aktif.');
        }

        return $employee;
    }

    /** @param array<string, mixed> $data */
    public function createSchedule(array $data, User $actor): EmployeeSchedule
    {
        return DB::transaction(function () use ($data, $actor): EmployeeSchedule {
            $employee = Employee::query()->lockForUpdate()->findOrFail((int) $data['employee_id']);
            $shift = WorkShift::query()->findOrFail((int) $data['work_shift_id']);
            $workLocationId = (int) ($data['work_location_id'] ?? $shift->work_location_id ?? $employee->work_location_id);
            if (! $actor->canAccessWorkLocation($workLocationId)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke lokasi jadwal ini.');
            }
            $location = WorkLocation::query()->findOrFail($workLocationId);
            if (! $this->canManageScheduleAt($actor, $location)) {
                throw ServiceException::validation('Jadwal hanya dapat diatur oleh kepala lokasi atau Super Admin.');
            }

            $date = Carbon::parse((string) $data['scheduled_date'])->startOfDay();
            $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
            $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
            if ($shift->is_cross_midnight || $end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            $payload = [
                'work_shift_id' => $shift->id,
                'work_location_id' => $workLocationId,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $end,
                'status' => $data['status'] ?? EmployeeScheduleStatus::SCHEDULED->value,
                'source' => 'manual',
                'employee_schedule_pattern_id' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ];
            $schedule = EmployeeSchedule::query()
                ->where('employee_id', $employee->id)
                ->whereDate('scheduled_date', $date)
                ->lockForUpdate()
                ->first();
            if ($schedule instanceof EmployeeSchedule) {
                $schedule->update($payload);
                $schedule->refresh();

                return $schedule;
            }

            return EmployeeSchedule::query()->create([
                'employee_id' => $employee->id,
                'scheduled_date' => $date,
                ...$payload,
            ]);
        });
    }

    /** @param array{work_location_id: int, effective_from: string, days: array<int|string, string|int>} $data */
    public function saveWeeklyPattern(Employee $employee, array $data, User $actor): EmployeeSchedulePattern
    {
        return DB::transaction(function () use ($employee, $data, $actor): EmployeeSchedulePattern {
            $locationId = (int) $data['work_location_id'];
            if (! $actor->canAccessWorkLocation($locationId) || (int) $employee->work_location_id !== $locationId) {
                throw ServiceException::validation('Karyawan dan pola jadwal harus berada pada lokasi penugasan Anda.');
            }
            $location = WorkLocation::query()->findOrFail($locationId);
            if (! $this->canManageScheduleAt($actor, $location)) {
                throw ServiceException::validation('Pola jadwal hanya dapat ditetapkan oleh kepala lokasi atau Super Admin.');
            }
            $effective = Carbon::parse($data['effective_from'])->startOfDay();
            $nextMonday = now()->startOfWeek(Carbon::MONDAY)->addWeek();
            if (! $effective->isMonday() || $effective->lessThan($nextMonday)) {
                throw ServiceException::validation('Pola jadwal harus mulai pada hari Senin berikutnya atau setelahnya.');
            }

            EmployeeSchedulePattern::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $effective->toDateString()))
                ->update(['effective_until' => $effective->copy()->subDay()->toDateString()]);

            $pattern = EmployeeSchedulePattern::query()->create([
                'employee_id' => $employee->id,
                'work_location_id' => $locationId,
                'effective_from' => $effective,
                'is_active' => true,
                'created_by' => $actor->id,
            ]);
            foreach (range(1, 7) as $weekday) {
                $value = (string) ($data['days'][$weekday] ?? 'off');
                $shift = $value === 'off' ? null : WorkShift::query()->find((int) $value);
                if ($value !== 'off' && (! $shift instanceof WorkShift || ($shift->work_location_id !== null && (int) $shift->work_location_id !== $locationId))) {
                    throw ServiceException::validation('Shift pada pola tidak sesuai dengan lokasi karyawan.');
                }
                $pattern->days()->create([
                    'weekday' => $weekday,
                    'work_shift_id' => $shift?->id,
                    'status' => $shift ? EmployeeScheduleStatus::SCHEDULED : EmployeeScheduleStatus::DAY_OFF,
                ]);
            }
            $this->generatePatternSchedules($pattern, 28);
            $this->audit->record('attendance.schedule_pattern_created', 'attendance', $actor, $pattern, [], ['employee_id' => $employee->id, 'effective_from' => $effective->toDateString()], location: $location);

            return $pattern->load(['days.workShift', 'employee']);
        });
    }

    public function generateAllPatternSchedules(int $days = 28): int
    {
        $count = 0;
        EmployeeSchedulePattern::query()->with(['days.workShift'])->where('is_active', true)
            ->whereDate('effective_from', '<=', now()->addDays($days)->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', now()->toDateString()))
            ->chunkById(100, function ($patterns) use (&$count, $days): void {
                foreach ($patterns as $pattern) {
                    $count += $this->generatePatternSchedules($pattern, $days);
                }
            });

        return $count;
    }

    public function generatePatternSchedules(EmployeeSchedulePattern $pattern, int $days = 28): int
    {
        $pattern->loadMissing('days.workShift');
        $today = now()->startOfDay();
        $patternStart = Carbon::parse($pattern->effective_from)->startOfDay();
        $start = $patternStart->greaterThan($today) ? $patternStart : $today;
        $end = now()->startOfDay()->addDays($days - 1);
        if ($pattern->effective_until && Carbon::parse($pattern->effective_until)->lt($end)) {
            $end = Carbon::parse($pattern->effective_until)->endOfDay();
        }
        $count = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $pattern->days->firstWhere('weekday', $date->isoWeekday());
            if ($day === null) {
                continue;
            }
            $existing = EmployeeSchedule::query()->where('employee_id', $pattern->employee_id)->whereDate('scheduled_date', $date)->first();
            if ($existing && $existing->source === 'manual') {
                continue;
            }
            $shift = $day->workShift;
            $startAt = $shift ? Carbon::parse($date->toDateString().' '.$shift->start_time) : null;
            $endAt = $shift ? Carbon::parse($date->toDateString().' '.$shift->end_time) : null;
            if ($shift && ($shift->is_cross_midnight || $endAt->lte($startAt))) {
                $endAt->addDay();
            }
            $payload = ['work_shift_id' => $shift?->id, 'work_location_id' => $pattern->work_location_id, 'scheduled_start_at' => $startAt, 'scheduled_end_at' => $endAt, 'status' => $day->status, 'source' => 'pattern', 'employee_schedule_pattern_id' => $pattern->id, 'created_by' => $pattern->created_by];
            if ($existing instanceof EmployeeSchedule) {
                $existing->update($payload);
            } else {
                EmployeeSchedule::query()->create(['employee_id' => $pattern->employee_id, 'scheduled_date' => $date, ...$payload]);
            }
            $count++;
        }

        return $count;
    }

    /** @param array<string, mixed> $data */
    public function checkIn(User $user, array $data): Attendance
    {
        return DB::transaction(function () use ($user, $data): Attendance {
            $employee = $this->employeeForUser($user);
            $now = isset($data['checked_at']) ? Carbon::parse((string) $data['checked_at']) : now();
            $schedule = $this->activeScheduleFor($employee, null, $now);
            if (! $schedule instanceof EmployeeSchedule || $this->scheduleStatus($schedule) !== EmployeeScheduleStatus::SCHEDULED) {
                throw ServiceException::validation('Tidak ada jadwal aktif untuk check-in saat ini.');
            }
            if (! $user->canAccessWorkLocation((int) $schedule->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke lokasi jadwal ini.');
            }

            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $this->scheduleDate($schedule))
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Attendance && $existing->check_in_at !== null) {
                throw ServiceException::validation('Check-in untuk jadwal ini sudah tercatat.');
            }

            $lateMinutes = $this->lateMinutes($schedule, $now);
            $status = $lateMinutes > 0 ? AttendanceStatus::LATE : AttendanceStatus::PRESENT;
            $payload = [
                'user_id' => $user->id,
                'work_location_id' => $schedule->work_location_id,
                'work_shift_id' => $schedule->work_shift_id,
                'employee_schedule_id' => $schedule->id,
                'attendance_date' => $this->scheduleDate($schedule),
                'check_in_at' => $now,
                'status' => $status,
                'verification_status' => AttendanceVerificationStatus::NOT_READY,
                'late_minutes' => $lateMinutes,
                'check_in_method' => 'login',
                'proof_path' => $data['proof_path'] ?? null,
                'device_info' => $data['device_info'] ?? null,
                'location_note' => $data['location_note'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ];

            if ($existing instanceof Attendance) {
                $existing->forceFill($payload)->save();
                $attendance = $existing->fresh(['employee', 'schedule.workShift', 'workLocation']);
            } else {
                $attendance = Attendance::query()->create(['employee_id' => $employee->id, ...$payload])->fresh(['employee', 'schedule.workShift', 'workLocation']);
            }
            $this->audit->record('attendance.checked_in', 'attendance', $user, $attendance, [], ['check_in_at' => $attendance->check_in_at, 'status' => $status->value], location: $attendance->workLocation);

            return $attendance;
        });
    }

    /** @param array<string, mixed> $data */
    public function checkOut(User $user, array $data): Attendance
    {
        return DB::transaction(function () use ($user, $data): Attendance {
            $employee = $this->employeeForUser($user);
            $attendance = Attendance::query()
                ->with('schedule.workShift')
                ->where('employee_id', $employee->id)
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->lockForUpdate()
                ->latest('check_in_at')
                ->first();
            if (! $attendance instanceof Attendance) {
                throw ServiceException::validation('Belum ada check-in aktif yang bisa dipulangkan.');
            }

            $this->assertReadyForCheckOut($attendance, $user);

            $now = isset($data['checked_at']) ? Carbon::parse((string) $data['checked_at']) : now();
            $schedule = $attendance->schedule;
            $early = $schedule instanceof EmployeeSchedule ? $this->earlyLeaveMinutes($schedule, $now) : 0;
            $checkInAt = $this->dateTime($attendance->getRawOriginal('check_in_at'));
            $worked = $checkInAt instanceof Carbon ? max(0, (int) $checkInAt->diffInMinutes($now, false)) : 0;
            $status = $early > 0 ? AttendanceStatus::EARLY_LEAVE : ($attendance->late_minutes > 0 ? AttendanceStatus::LATE : AttendanceStatus::PRESENT);
            $scheduledEndAt = $schedule instanceof EmployeeSchedule ? $this->scheduledEndAt($schedule) : null;

            $attendance->forceFill([
                'check_out_at' => $now,
                'status' => $status,
                'verification_status' => AttendanceVerificationStatus::PENDING,
                'verified_by' => null,
                'verified_at' => null,
                'verification_note' => null,
                'early_leave_minutes' => $early,
                'worked_minutes' => $worked,
                'overtime_minutes' => $scheduledEndAt instanceof Carbon && $now->greaterThan($scheduledEndAt)
                    ? (int) $scheduledEndAt->diffInMinutes($now)
                    : 0,
                'check_out_method' => 'login',
                'device_info' => $data['device_info'] ?? $attendance->device_info,
                'location_note' => $data['location_note'] ?? $attendance->location_note,
                'notes' => $data['notes'] ?? $attendance->notes,
            ])->save();

            $attendance->load('workLocation');
            $this->audit->record('attendance.checked_out', 'attendance', $user, $attendance, [], ['check_out_at' => $attendance->check_out_at, 'verification_status' => AttendanceVerificationStatus::PENDING->value], location: $attendance->workLocation);

            return $attendance->fresh(['employee', 'schedule.workShift']);
        });
    }

    public function supervisorCheckOut(Attendance $attendance, User $actor, string $reason, ?Request $request = null): Attendance
    {
        return DB::transaction(function () use ($attendance, $actor, $reason, $request): Attendance {
            $locked = Attendance::query()->with(['employee.user.roles', 'workLocation', 'schedule.workShift'])->lockForUpdate()->findOrFail($attendance->id);
            if ($locked->check_in_at === null || $locked->check_out_at !== null) {
                throw ServiceException::validation('Absensi ini tidak sedang aktif.');
            }
            if ((int) $locked->user_id === (int) $actor->id || ! $this->canVerify($actor, $locked)) {
                throw ServiceException::validation('Anda tidak berwenang mencatat pulang pada lokasi ini.');
            }
            $this->applyCheckOut($locked, now(), 'supervisor');
            $this->audit->record('attendance.supervisor_checked_out', 'attendance', $actor, $locked, [], ['check_out_at' => $locked->check_out_at], $reason, $request, $locked->workLocation, 'warning');

            return $locked->fresh(['employee', 'workLocation']);
        });
    }

    public function verify(Attendance $attendance, User $actor, bool $approved, ?string $note = null, ?Request $request = null): Attendance
    {
        return DB::transaction(function () use ($attendance, $actor, $approved, $note, $request): Attendance {
            $locked = Attendance::query()->with(['employee.user.roles', 'workLocation'])->lockForUpdate()->findOrFail($attendance->id);
            if ($locked->check_out_at === null || $locked->getRawOriginal('verification_status') !== AttendanceVerificationStatus::PENDING->value) {
                throw ServiceException::validation('Absensi belum siap atau sudah diverifikasi.');
            }
            if ((int) $locked->user_id === (int) $actor->id) {
                throw ServiceException::validation('Anda tidak dapat memverifikasi absensi sendiri.');
            }
            if (! $this->canVerify($actor, $locked)) {
                throw ServiceException::validation('Anda tidak berwenang memverifikasi absensi ini.');
            }
            if (! $approved && ! filled($note)) {
                throw ServiceException::validation('Alasan penolakan wajib diisi.');
            }
            $before = $locked->only(['verification_status', 'verified_by', 'verified_at', 'verification_note']);
            $locked->forceFill([
                'verification_status' => $approved ? AttendanceVerificationStatus::APPROVED : AttendanceVerificationStatus::REJECTED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_note' => $note,
            ])->save();
            $this->audit->record($approved ? 'attendance.approved' : 'attendance.rejected', 'attendance', $actor, $locked, $before, $locked->only(['verification_status', 'verified_by', 'verified_at', 'verification_note']), $note, $request, $locked->workLocation, $approved ? 'info' : 'warning');

            return $locked->fresh(['employee', 'verifier']);
        });
    }

    public function activeAttendanceForCashShift(User $cashier, WorkLocation $workLocation, ?Carbon $asOf = null): Attendance
    {
        $asOf ??= now();
        $employee = $this->employeeForUser($cashier);
        $schedule = $this->activeScheduleFor($employee, $workLocation, $asOf);
        if (! $schedule instanceof EmployeeSchedule || $this->scheduleStatus($schedule) !== EmployeeScheduleStatus::SCHEDULED) {
            throw ServiceException::validation('Kasir belum memiliki jadwal aktif pada cabang ini.');
        }

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('employee_schedule_id', $schedule->id)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->first();
        if (! $attendance instanceof Attendance) {
            throw ServiceException::validation('Kasir wajib check-in sebelum membuka shift POS.');
        }

        return $attendance;
    }

    /** @param array<string, mixed> $data */
    public function submitRequest(User $user, array $data): AttendanceRequest
    {
        return DB::transaction(function () use ($user, $data): AttendanceRequest {
            $employee = $this->employeeForUser($user);
            $start = Carbon::parse((string) $data['start_at']);
            $end = Carbon::parse((string) $data['end_at']);
            if ($end->lessThanOrEqualTo($start)) {
                throw ServiceException::validation('Waktu selesai pengajuan harus setelah waktu mulai.');
            }
            $overlap = AttendanceRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', '!=', AttendanceRequestStatus::REJECTED->value)
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->exists();
            if ($overlap) {
                throw ServiceException::validation('Pengajuan bentrok dengan pengajuan lain.');
            }

            return AttendanceRequest::query()->create([
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'work_location_id' => $employee->work_location_id,
                'type' => $data['type'],
                'start_at' => $start,
                'end_at' => $end,
                'reason' => $data['reason'],
                'proof_path' => $data['proof_path'] ?? null,
                'replacement_employee_id' => $data['replacement_employee_id'] ?? null,
                'status' => AttendanceRequestStatus::PENDING,
                'requested_by' => $user->id,
            ]);
        });
    }

    public function approveRequest(AttendanceRequest $requestModel, User $approver, bool $approved, ?string $note = null): AttendanceRequest
    {
        return DB::transaction(function () use ($requestModel, $approver, $approved, $note): AttendanceRequest {
            $requestModel = AttendanceRequest::query()->with('employee')->lockForUpdate()->findOrFail($requestModel->id);
            if ($this->requestStatus($requestModel) !== AttendanceRequestStatus::PENDING) {
                throw ServiceException::validation('Pengajuan sudah diproses.');
            }
            if (! $approver->canAccessWorkLocation((int) $requestModel->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke lokasi pengajuan ini.');
            }

            $requestModel->forceFill([
                'status' => $approved ? AttendanceRequestStatus::APPROVED : AttendanceRequestStatus::REJECTED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ])->save();

            if ($approved) {
                $this->createLeaveAttendance($requestModel, $approver);
            }

            return $requestModel->fresh(['employee']);
        });
    }

    /** @param array<string, mixed> $data */
    public function submitCorrection(Attendance $attendance, array $data, User $actor): AttendanceCorrection
    {
        if (! $actor->canAccessWorkLocation((int) $attendance->work_location_id)) {
            throw ServiceException::validation('Anda tidak memiliki akses ke lokasi absensi ini.');
        }

        return DB::transaction(function () use ($attendance, $data, $actor): AttendanceCorrection {
            $correction = AttendanceCorrection::query()->create([
                'attendance_id' => $attendance->id,
                'employee_id' => $attendance->employee_id,
                'requested_by' => $actor->id,
                'old_check_in_at' => $attendance->check_in_at,
                'old_check_out_at' => $attendance->check_out_at,
                'proposed_check_in_at' => $data['proposed_check_in_at'] ?? null,
                'proposed_check_out_at' => $data['proposed_check_out_at'] ?? null,
                'reason' => $data['reason'],
                'proof_path' => $data['proof_path'] ?? null,
                'status' => AttendanceRequestStatus::PENDING,
                'before_snapshot' => $attendance->only(['check_in_at', 'check_out_at', 'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes']),
            ]);
            $this->audit->record('attendance.correction_requested', 'attendance', $actor, $correction, [], $correction->only(['attendance_id', 'proposed_check_in_at', 'proposed_check_out_at']), (string) $data['reason'], location: WorkLocation::query()->find($attendance->work_location_id));

            return $correction;
        });
    }

    public function approveCorrection(AttendanceCorrection $correction, User $approver, bool $approved, ?string $note = null): AttendanceCorrection
    {
        return DB::transaction(function () use ($correction, $approver, $approved, $note): AttendanceCorrection {
            $correction = AttendanceCorrection::query()->with('attendance.schedule')->lockForUpdate()->findOrFail($correction->id);
            if ($this->correctionStatus($correction) !== AttendanceRequestStatus::PENDING) {
                throw ServiceException::validation('Koreksi sudah diproses.');
            }
            $attendance = Attendance::query()->lockForUpdate()->findOrFail($correction->attendance_id);
            if (! $approver->canAccessWorkLocation((int) $attendance->work_location_id)) {
                throw ServiceException::validation('Anda tidak memiliki akses ke lokasi absensi ini.');
            }

            if ($approved) {
                $checkIn = $this->dateTime($correction->getRawOriginal('proposed_check_in_at')) ?? $this->dateTime($attendance->getRawOriginal('check_in_at'));
                $checkOut = $this->dateTime($correction->getRawOriginal('proposed_check_out_at')) ?? $this->dateTime($attendance->getRawOriginal('check_out_at'));
                $this->applyAttendanceTimes($attendance, $checkIn, $checkOut);
                if ($checkOut instanceof Carbon) {
                    $attendance->forceFill([
                        'verification_status' => AttendanceVerificationStatus::PENDING,
                        'verified_by' => null,
                        'verified_at' => null,
                        'verification_note' => null,
                    ])->save();
                }
                $correction->forceFill(['after_snapshot' => $attendance->fresh()->only(['check_in_at', 'check_out_at', 'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes'])]);
            }

            $correction->forceFill([
                'status' => $approved ? AttendanceRequestStatus::APPROVED : AttendanceRequestStatus::REJECTED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ])->save();
            $this->audit->record($approved ? 'attendance.correction_approved' : 'attendance.correction_rejected', 'attendance', $approver, $correction, [], $correction->only(['status', 'approved_by', 'approved_at']), $note, location: WorkLocation::query()->find($attendance->work_location_id), severity: $approved ? 'info' : 'warning');

            return $correction->fresh(['attendance']);
        });
    }

    public function activeScheduleFor(Employee $employee, ?WorkLocation $location = null, ?Carbon $asOf = null): ?EmployeeSchedule
    {
        $asOf ??= now();
        $windowStart = $asOf->copy()->subHours(8);
        $windowEnd = $asOf->copy()->addHours(8);

        return EmployeeSchedule::query()
            ->with('workShift')
            ->where('employee_id', $employee->id)
            ->when($location instanceof WorkLocation, fn ($query) => $query->where('work_location_id', $location->id))
            ->where('status', EmployeeScheduleStatus::SCHEDULED->value)
            ->where('scheduled_start_at', '<=', $windowEnd)
            ->where('scheduled_end_at', '>=', $windowStart)
            ->orderByDesc('scheduled_start_at')
            ->first();
    }

    private function lateMinutes(EmployeeSchedule $schedule, Carbon $checkIn): int
    {
        $shift = $schedule->workShift;
        $tolerance = $shift instanceof WorkShift ? $shift->tolerance_late_minutes : 0;
        $deadline = $this->scheduledStartAt($schedule)?->addMinutes($tolerance);

        return $deadline instanceof Carbon && $checkIn->greaterThan($deadline) ? (int) $deadline->diffInMinutes($checkIn) : 0;
    }

    private function earlyLeaveMinutes(EmployeeSchedule $schedule, Carbon $checkOut): int
    {
        $shift = $schedule->workShift;
        $tolerance = $shift instanceof WorkShift ? $shift->tolerance_early_leave_minutes : 0;
        $threshold = $this->scheduledEndAt($schedule)?->subMinutes($tolerance);

        return $threshold instanceof Carbon && $checkOut->lessThan($threshold) ? (int) $checkOut->diffInMinutes($threshold) : 0;
    }

    private function createLeaveAttendance(AttendanceRequest $requestModel, User $approver): void
    {
        $type = $this->requestType($requestModel);
        $status = match ($type) {
            AttendanceRequestType::SICK => AttendanceStatus::SICK,
            AttendanceRequestType::LEAVE => AttendanceStatus::LEAVE,
            AttendanceRequestType::OVERTIME => AttendanceStatus::OVERTIME,
            default => AttendanceStatus::PERMISSION,
        };
        $startAt = $this->dateTime($requestModel->getRawOriginal('start_at'));
        if (! $startAt instanceof Carbon) {
            return;
        }
        $date = $startAt->toDateString();
        $attendance = Attendance::query()->where('employee_id', $requestModel->employee_id)->whereDate('attendance_date', $date)->first();
        $payload = [
            'user_id' => $requestModel->user_id,
            'work_location_id' => $requestModel->work_location_id,
            'attendance_date' => $date,
            'status' => $status,
            'notes' => $requestModel->reason,
            'created_by' => $requestModel->requested_by,
            'approved_by' => $approver->id,
            'metadata' => ['attendance_request_id' => $requestModel->id],
        ];

        if ($attendance instanceof Attendance) {
            $attendance->forceFill($payload)->save();

            return;
        }

        Attendance::query()->create(['employee_id' => $requestModel->employee_id, ...$payload]);
    }

    private function applyAttendanceTimes(Attendance $attendance, ?Carbon $checkIn, ?Carbon $checkOut): void
    {
        $schedule = $attendance->schedule;
        $late = $schedule instanceof EmployeeSchedule && $checkIn instanceof Carbon ? $this->lateMinutes($schedule, $checkIn) : 0;
        $early = $schedule instanceof EmployeeSchedule && $checkOut instanceof Carbon ? $this->earlyLeaveMinutes($schedule, $checkOut) : 0;
        $worked = $checkIn instanceof Carbon && $checkOut instanceof Carbon ? max(0, (int) $checkIn->diffInMinutes($checkOut, false)) : 0;
        $status = $early > 0 ? AttendanceStatus::EARLY_LEAVE : ($late > 0 ? AttendanceStatus::LATE : AttendanceStatus::PRESENT);

        $attendance->forceFill([
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $status,
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'worked_minutes' => $worked,
        ])->save();
    }

    private function assertReadyForCheckOut(Attendance $attendance, User $user): void
    {
        $hasOpenCashShift = CashShift::query()
            ->where('cashier_user_id', $user->id)
            ->whereIn('status', [CashShiftStatus::OPEN->value, CashShiftStatus::REJECTED->value])
            ->exists();
        if ($hasOpenCashShift) {
            throw ServiceException::validation('Selesaikan closing shift kas sebelum melakukan absen pulang.');
        }

        $checklistComplete = WorkChecklist::query()
            ->where('user_id', $user->id)
            ->where('work_location_id', $attendance->work_location_id)
            ->where('frequency', WorkChecklistFrequency::DAILY->value)
            ->whereDate('period_start', $attendance->attendance_date)
            ->where('status', WorkChecklistStatus::COMPLETED->value)
            ->exists();
        if (! $checklistComplete) {
            throw ServiceException::validation('Selesaikan checklist kerja harian sebelum melakukan absen pulang.');
        }
    }

    private function applyCheckOut(Attendance $attendance, Carbon $when, string $method): void
    {
        $schedule = $attendance->schedule;
        $early = $schedule instanceof EmployeeSchedule ? $this->earlyLeaveMinutes($schedule, $when) : 0;
        $checkIn = $this->dateTime($attendance->getRawOriginal('check_in_at'));
        $worked = $checkIn instanceof Carbon ? max(0, (int) $checkIn->diffInMinutes($when, false)) : 0;
        $status = $early > 0 ? AttendanceStatus::EARLY_LEAVE : ($attendance->late_minutes > 0 ? AttendanceStatus::LATE : AttendanceStatus::PRESENT);
        $scheduledEnd = $schedule instanceof EmployeeSchedule ? $this->scheduledEndAt($schedule) : null;

        $attendance->forceFill([
            'check_out_at' => $when,
            'status' => $status,
            'verification_status' => AttendanceVerificationStatus::PENDING,
            'early_leave_minutes' => $early,
            'worked_minutes' => $worked,
            'overtime_minutes' => $scheduledEnd instanceof Carbon && $when->greaterThan($scheduledEnd) ? (int) $scheduledEnd->diffInMinutes($when) : 0,
            'check_out_method' => $method,
            'verified_by' => null,
            'verified_at' => null,
            'verification_note' => null,
        ])->save();
    }

    private function canVerify(User $actor, Attendance $attendance): bool
    {
        if (! $actor->can('attendance.approve') || ! $actor->canAccessWorkLocation((int) $attendance->work_location_id)) {
            return false;
        }
        if ($actor->hasAnyRole(['super_admin', 'owner_approver'])) {
            return true;
        }

        $roles = $attendance->employee?->user?->roles?->pluck('name') ?? collect();
        if ($actor->hasRole('kepala_toko') && $attendance->workLocation?->type === 'branch') {
            return $roles->intersect(['staf_toko', 'kasir', 'supervisor_shift'])->isNotEmpty();
        }
        if ($actor->hasRole('kepala_gudang') && $attendance->workLocation?->type === 'warehouse') {
            return $roles->intersect(['staff_gudang', 'picker_packer'])->isNotEmpty();
        }

        return false;
    }

    private function canManageScheduleAt(User $actor, WorkLocation $location): bool
    {
        return $actor->hasRole('super_admin')
            || ($location->type === 'branch' && $actor->hasRole('kepala_toko'))
            || ($location->type === 'warehouse' && $actor->hasRole('kepala_gudang'));
    }

    private function scheduleStatus(EmployeeSchedule $schedule): EmployeeScheduleStatus
    {
        return EmployeeScheduleStatus::from((string) $schedule->getRawOriginal('status'));
    }

    private function requestStatus(AttendanceRequest $request): AttendanceRequestStatus
    {
        return AttendanceRequestStatus::from((string) $request->getRawOriginal('status'));
    }

    private function correctionStatus(AttendanceCorrection $correction): AttendanceRequestStatus
    {
        return AttendanceRequestStatus::from((string) $correction->getRawOriginal('status'));
    }

    private function requestType(AttendanceRequest $request): AttendanceRequestType
    {
        return AttendanceRequestType::from((string) $request->getRawOriginal('type'));
    }

    private function scheduleDate(EmployeeSchedule $schedule): string
    {
        return Carbon::parse((string) $schedule->getRawOriginal('scheduled_date'))->toDateString();
    }

    private function scheduledStartAt(EmployeeSchedule $schedule): ?Carbon
    {
        return $this->dateTime($schedule->getRawOriginal('scheduled_start_at'));
    }

    private function scheduledEndAt(EmployeeSchedule $schedule): ?Carbon
    {
        return $this->dateTime($schedule->getRawOriginal('scheduled_end_at'));
    }

    private function dateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse((string) $value);
    }
}
