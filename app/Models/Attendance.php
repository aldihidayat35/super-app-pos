<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['employee_id', 'user_id', 'work_location_id', 'work_shift_id', 'employee_schedule_id', 'attendance_date', 'check_in_at', 'check_out_at', 'status', 'late_minutes', 'early_leave_minutes', 'worked_minutes', 'overtime_minutes', 'check_in_method', 'check_out_method', 'proof_path', 'device_info', 'location_note', 'notes', 'metadata', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<WorkShift, $this> */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /** @return BelongsTo<EmployeeSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmployeeSchedule::class, 'employee_schedule_id');
    }
}
