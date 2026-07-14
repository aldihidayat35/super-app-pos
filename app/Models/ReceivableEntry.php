<?php

namespace App\Models;

use App\Enums\ReceivableEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableEntry extends Model
{
    protected $fillable = ['receivable_id', 'customer_id', 'entry_type', 'amount', 'balance_before', 'balance_after', 'source_type', 'source_id', 'source_no', 'actor_user_id', 'notes', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'entry_type' => ReceivableEntryType::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Receivable, $this> */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
