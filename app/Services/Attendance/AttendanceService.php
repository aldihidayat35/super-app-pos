<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeScheduleStatus;
use App\Exceptions\ServiceException;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
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

            $date = Carbon::parse((string) $data['scheduled_date'])->startOfDay();
            $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
            $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
            if ($shift->is_cross_midnight || $end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }

            return EmployeeSchedule::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'scheduled_date' => $date->toDateString()],
                [
                    'work_shift_id' => $shift->id,
                    'work_location_id' => $workLocationId,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'status' => $data['status'] ?? EmployeeScheduleStatus::SCHEDULED->value,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ],
            );
        });
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
                'late_minutes' => $lateMinutes,
                'check_in_method' => $data['method'] ?? 'login',
                'proof_path' => $data['proof_path'] ?? null,
                'device_info' => $data['device_info'] ?? null,
                'location_note' => $data['location_note'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ];

            if ($existing instanceof Attendance) {
                $existing->forceFill($payload)->save();

                return $existing->fresh(['employee', 'schedule.workShift']);
            }

            return Attendance::query()->create(['employee_id' => $employee->id, ...$payload])->fresh(['employee', 'schedule.workShift']);
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
                'early_leave_minutes' => $early,
                'worked_minutes' => $worked,
                'overtime_minutes' => $scheduledEndAt instanceof Carbon && $now->greaterThan($scheduledEndAt)
                    ? (int) $scheduledEndAt->diffInMinutes($now)
                    : 0,
                'check_out_method' => $data['method'] ?? 'login',
                'device_info' => $data['device_info'] ?? $attendance->device_info,
                'location_note' => $data['location_note'] ?? $attendance->location_note,
                'notes' => $data['notes'] ?? $attendance->notes,
            ])->save();

            return $attendance->fresh(['employee', 'schedule.workShift']);
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

        return AttendanceCorrection::query()->create([
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
                $correction->forceFill(['after_snapshot' => $attendance->fresh()->only(['check_in_at', 'check_out_at', 'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes'])]);
            }

            $correction->forceFill([
                'status' => $approved ? AttendanceRequestStatus::APPROVED : AttendanceRequestStatus::REJECTED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_note' => $note,
            ])->save();

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
