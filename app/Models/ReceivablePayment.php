<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivablePayment extends Model
{
    protected $fillable = ['number', 'customer_id', 'cash_shift_id', 'received_by', 'method', 'amount', 'payment_date', 'reference_no', 'proof_path', 'idempotency_key', 'notes'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<ReceivablePaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceivablePaymentAllocation::class);
    }
}
