<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffBonusPayment extends Model
{
    protected $fillable = ['staff_bonus_result_id', 'amount', 'paid_on', 'payment_method', 'reference_no', 'proof_path', 'notes', 'paid_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<StaffBonusResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(StaffBonusResult::class, 'staff_bonus_result_id');
    }
}
