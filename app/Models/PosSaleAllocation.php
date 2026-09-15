<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleAllocation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_quantity' => 'decimal:4', 'returned_quantity' => 'decimal:4',
            'normal_hpp_unit' => 'decimal:2', 'actual_cost_unit' => 'decimal:2',
            'revenue_amount' => 'decimal:2', 'normal_cogs_amount' => 'decimal:2',
            'actual_cogs_amount' => 'decimal:2', 'actual_margin_amount' => 'decimal:2',
            'lost_margin_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<EmergencyPurchaseItem, $this> */
    public function emergencyItem(): BelongsTo
    {
        return $this->belongsTo(EmergencyPurchaseItem::class, 'emergency_purchase_item_id');
    }

    /** @return BelongsTo<PosSaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }
}
