<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBatch extends Model
{
    protected $fillable = ['product_id', 'supplier_id', 'stock_id', 'goods_receipt_id', 'goods_receipt_item_id', 'batch_no', 'received_at', 'expires_at', 'cost_price', 'quantity_on_hand', 'quantity_reserved', 'status'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'expires_at' => 'date', 'cost_price' => 'decimal:2', 'quantity_on_hand' => 'decimal:4', 'quantity_reserved' => 'decimal:4'];
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

    /** @return BelongsTo<Stock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
