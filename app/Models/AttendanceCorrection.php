<?php

namespace App\Models;

use App\Enums\AttendanceRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    protected $fillable = ['attendance_id', 'employee_id', 'requested_by', 'old_check_in_at', 'old_check_out_at', 'proposed_check_in_at', 'proposed_check_out_at', 'reason', 'proof_path', 'status', 'approved_by', 'approved_at', 'approval_note', 'before_snapshot', 'after_snapshot'];

    protected function casts(): array
    {
        return [
            'old_check_in_at' => 'datetime',
            'old_check_out_at' => 'datetime',
            'proposed_check_in_at' => 'datetime',
            'proposed_check_out_at' => 'datetime',
            'status' => AttendanceRequestStatus::class,
            'approved_at' => 'datetime',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
