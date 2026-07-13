<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCostHistory extends Model
{
    protected $fillable = ['product_id', 'supplier_id', 'goods_receipt_id', 'goods_receipt_item_id', 'method', 'qty_before', 'qty_incoming', 'qty_after', 'hpp_before', 'incoming_cost', 'landed_cost_allocated', 'hpp_after', 'effective_at'];

    protected function casts(): array
    {
        return [
            'qty_before' => 'decimal:4',
            'qty_incoming' => 'decimal:4',
            'qty_after' => 'decimal:4',
            'hpp_before' => 'decimal:2',
            'incoming_cost' => 'decimal:2',
            'landed_cost_allocated' => 'decimal:2',
            'hpp_after' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
