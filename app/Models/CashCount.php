<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCount extends Model
{
    protected $fillable = ['cash_shift_id', 'denomination', 'quantity', 'amount'];

    protected function casts(): array
    {
        return [
            'denomination' => 'integer',
            'quantity' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CashShift, $this> */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }
}
