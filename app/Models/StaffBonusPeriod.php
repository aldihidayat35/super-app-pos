<?php

namespace App\Models;

use App\Enums\StaffBonusPeriodStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StaffBonusPeriodStatus $status
 * @property int $month
 * @property int $year
 * @property int|null $work_location_id
 * @property string $total_bonus_amount
 * @property-read WorkLocation|null $workLocation
 * @property-read StaffBonusProgram $program
 * @property-read Collection<int, StaffBonusResult> $results
 */
class StaffBonusPeriod extends Model
{
    protected $fillable = ['staff_bonus_program_id', 'work_location_id', 'month', 'year', 'status', 'program_snapshot', 'total_bonus_amount', 'last_calculated_at', 'submitted_at', 'submitted_by', 'approval_request_id', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'decision_note', 'closed_at'];

    protected function casts(): array
    {
        return ['status' => StaffBonusPeriodStatus::class, 'program_snapshot' => 'array', 'total_bonus_amount' => 'decimal:2', 'last_calculated_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<StaffBonusProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(StaffBonusProgram::class, 'staff_bonus_program_id');
    }

    /** @return HasMany<StaffBonusResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(StaffBonusResult::class);
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
