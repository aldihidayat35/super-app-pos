<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRule extends Model
{
    protected $fillable = [
        'name', 'channel', 'branch_id', 'customer_category', 'margin_method', 'minimum_margin_percent',
        'minimum_margin_amount', 'overpricing_tolerance_percent', 'max_discount_percent',
        'approval_threshold_amount', 'priority', 'starts_at', 'ends_at', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'minimum_margin_percent' => 'decimal:2',
            'minimum_margin_amount' => 'decimal:2',
            'overpricing_tolerance_percent' => 'decimal:2',
            'max_discount_percent' => 'decimal:2',
            'approval_threshold_amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
