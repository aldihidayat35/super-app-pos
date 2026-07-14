<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = ['work_location_id', 'code', 'name', 'start_time', 'end_time', 'is_cross_midnight', 'tolerance_late_minutes', 'tolerance_early_leave_minutes', 'break_minutes', 'work_days', 'effective_from', 'effective_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_cross_midnight' => 'boolean',
            'tolerance_late_minutes' => 'integer',
            'tolerance_early_leave_minutes' => 'integer',
            'break_minutes' => 'integer',
            'work_days' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<EmployeeSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }
}
