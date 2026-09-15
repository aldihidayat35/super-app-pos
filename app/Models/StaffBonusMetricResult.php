<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBonusMetricResult extends Model
{
    protected $fillable = ['staff_bonus_result_id', 'metric_key', 'label', 'scope', 'target_value', 'actual_value', 'score', 'weight_percentage', 'weighted_score', 'source_snapshot'];

    protected function casts(): array
    {
        return ['target_value' => 'decimal:4', 'actual_value' => 'decimal:4', 'score' => 'decimal:2', 'weight_percentage' => 'decimal:4', 'weighted_score' => 'decimal:2', 'source_snapshot' => 'array'];
    }

    /** @return BelongsTo<StaffBonusResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(StaffBonusResult::class, 'staff_bonus_result_id');
    }
}
