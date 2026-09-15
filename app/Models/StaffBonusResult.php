<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $user_id
 * @property int|null $work_location_id
 * @property string $role_name
 * @property string $maximum_bonus_amount
 * @property string $bonus_amount
 * @property string $payment_status
 * @property-read StaffBonusPeriod $period
 * @property-read WorkLocation|null $workLocation
 * @property-read User $user
 * @property-read Collection<int, StaffBonusMetricResult> $metrics
 * @property-read StaffBonusPayment|null $payment
 */
class StaffBonusResult extends Model
{
    protected $fillable = ['staff_bonus_period_id', 'user_id', 'work_location_id', 'role_name', 'maximum_bonus_amount', 'final_score', 'calculated_bonus_amount', 'adjustment_amount', 'bonus_amount', 'payment_status', 'employee_snapshot', 'calculated_at'];

    protected function casts(): array
    {
        return ['maximum_bonus_amount' => 'decimal:2', 'final_score' => 'decimal:2', 'calculated_bonus_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'bonus_amount' => 'decimal:2', 'employee_snapshot' => 'array', 'calculated_at' => 'datetime'];
    }

    /** @return BelongsTo<StaffBonusPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(StaffBonusPeriod::class, 'staff_bonus_period_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StaffBonusMetricResult, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(StaffBonusMetricResult::class)->orderBy('id');
    }

    /** @return HasOne<StaffBonusPayment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(StaffBonusPayment::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
