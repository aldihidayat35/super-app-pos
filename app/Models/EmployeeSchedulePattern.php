<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSchedulePattern extends Model
{
    protected $fillable = ['employee_id', 'work_location_id', 'effective_from', 'effective_until', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean'];
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

    /** @return HasMany<EmployeeSchedulePatternDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(EmployeeSchedulePatternDay::class)->orderBy('weekday');
    }
}
