<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLimit extends Model
{
    protected $fillable = ['customer_id', 'credit_limit', 'payment_term_days', 'current_balance', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'payment_term_days' => 'integer', 'current_balance' => 'decimal:2', 'effective_from' => 'date'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
