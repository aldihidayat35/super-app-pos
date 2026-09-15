<?php

namespace App\Models;

use App\Enums\StaffBonusProgramStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property StaffBonusProgramStatus $status
 * @property int $month
 * @property int $year
 * @property int|null $work_location_id
 * @property string $role_name
 * @property string $maximum_bonus_amount
 * @property-read WorkLocation|null $workLocation
 * @property-read Collection<int, StaffBonusProgramMetric> $metrics
 * @property-read Collection<int, StaffBonusProgramUser> $assignments
 * @property-read StaffBonusPeriod|null $period
 */
class StaffBonusProgram extends Model
{
    protected $fillable = ['program_key', 'version', 'name', 'role_name', 'work_location_id', 'month', 'year', 'maximum_bonus_amount', 'status', 'created_by', 'activated_by', 'legacy_sales_target_id', 'activated_at', 'configuration_snapshot'];

    protected function casts(): array
    {
        return ['status' => StaffBonusProgramStatus::class, 'maximum_bonus_amount' => 'decimal:2', 'activated_at' => 'datetime', 'configuration_snapshot' => 'array'];
    }

    /** @return HasMany<StaffBonusProgramMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(StaffBonusProgramMetric::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<StaffBonusProgramUser, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(StaffBonusProgramUser::class);
    }

    /** @return HasOne<StaffBonusPeriod, $this> */
    public function period(): HasOne
    {
        return $this->hasOne(StaffBonusPeriod::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
