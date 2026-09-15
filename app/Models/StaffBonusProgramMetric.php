<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $metric_key
 * @property string $label
 * @property string $scope
 * @property string $target_value
 * @property string $weight_percentage
 */
class StaffBonusProgramMetric extends Model
{
    protected $fillable = ['staff_bonus_program_id', 'metric_key', 'label', 'scope', 'target_value', 'weight_percentage', 'sort_order'];

    protected function casts(): array
    {
        return ['target_value' => 'decimal:4', 'weight_percentage' => 'decimal:4'];
    }

    /** @return BelongsTo<StaffBonusProgram, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(StaffBonusProgram::class, 'staff_bonus_program_id');
    }
}
