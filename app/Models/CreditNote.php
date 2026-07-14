<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    protected $fillable = ['number', 'receivable_id', 'customer_id', 'type', 'amount', 'status', 'reason', 'created_by', 'approved_by', 'approved_at', 'approval_note'];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Receivable, $this> */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
