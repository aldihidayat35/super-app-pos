<?php

namespace App\Models;

use App\Enums\CreditLimitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLimit extends Model
{
    protected $fillable = ['customer_id', 'credit_limit', 'payment_term_days', 'approval_threshold_amount', 'max_overdue_days', 'current_balance', 'status', 'effective_from', 'blocked_at', 'blocked_by', 'blocked_reason', 'notes'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'payment_term_days' => 'integer', 'approval_threshold_amount' => 'decimal:2', 'max_overdue_days' => 'integer', 'current_balance' => 'decimal:2', 'status' => CreditLimitStatus::class, 'effective_from' => 'date', 'blocked_at' => 'datetime'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
