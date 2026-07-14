<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePaymentAllocation extends Model
{
    protected $fillable = ['receivable_payment_id', 'receivable_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<ReceivablePayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(ReceivablePayment::class, 'receivable_payment_id');
    }

    /** @return BelongsTo<Receivable, $this> */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
