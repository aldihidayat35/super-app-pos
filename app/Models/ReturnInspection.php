<?php

namespace App\Models;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnInspection extends Model
{
    protected $fillable = [
        'return_id', 'return_item_id', 'checker_user_id', 'qc_result', 'condition', 'quantity_good',
        'quantity_damaged', 'quantity_rejected', 'loss_value', 'responsible_party', 'evidence_path',
        'notes', 'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ReturnCondition::class,
            'quantity_good' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'loss_value' => 'decimal:2',
            'inspected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ReturnDocument, $this> */
    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(ReturnDocument::class, 'return_id');
    }

    /** @return BelongsTo<ReturnItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class, 'return_item_id');
    }
}
