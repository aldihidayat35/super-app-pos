<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBonusAdjustment extends Model
{
    protected $fillable = ['source_result_id', 'target_result_id', 'amount', 'reason', 'created_by', 'applied_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'applied_at' => 'datetime'];
    }

    /** @return BelongsTo<StaffBonusResult, $this> */
    public function sourceResult(): BelongsTo
    {
        return $this->belongsTo(StaffBonusResult::class, 'source_result_id');
    }

    /** @return BelongsTo<StaffBonusResult, $this> */
    public function targetResult(): BelongsTo
    {
        return $this->belongsTo(StaffBonusResult::class, 'target_result_id');
    }
}
