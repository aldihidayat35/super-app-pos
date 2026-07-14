<?php

namespace App\Models;

use App\Enums\EmployeeScheduleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    protected $fillable = ['employee_id', 'work_shift_id', 'work_location_id', 'scheduled_date', 'scheduled_start_at', 'scheduled_end_at', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'status' => EmployeeScheduleStatus::class,
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<WorkShift, $this> */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
