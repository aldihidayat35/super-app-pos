<?php

namespace App\Models;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRequest extends Model
{
    protected $fillable = ['employee_id', 'user_id', 'work_location_id', 'type', 'start_at', 'end_at', 'reason', 'proof_path', 'replacement_employee_id', 'status', 'requested_by', 'approved_by', 'approved_at', 'approval_note'];

    protected function casts(): array
    {
        return [
            'type' => AttendanceRequestType::class,
            'status' => AttendanceRequestStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
