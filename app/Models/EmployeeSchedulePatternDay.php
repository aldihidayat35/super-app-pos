<?php

namespace App\Models;

use App\Enums\EmployeeScheduleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedulePatternDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['employee_schedule_pattern_id', 'weekday', 'work_shift_id', 'status'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'status' => EmployeeScheduleStatus::class];
    }

    /** @return BelongsTo<EmployeeSchedulePattern, $this> */
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(EmployeeSchedulePattern::class, 'employee_schedule_pattern_id');
    }

    /** @return BelongsTo<WorkShift, $this> */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }
}
