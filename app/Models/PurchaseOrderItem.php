<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'unit_id', 'product_sku_snapshot', 'product_name_snapshot', 'unit_name_snapshot', 'conversion_factor_snapshot', 'quantity_ordered', 'quantity_received', 'unit_price', 'discount_amount', 'tax_amount', 'subtotal'];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function outstandingQuantity(): string
    {
        return Decimal::sub((string) $this->quantity_ordered, (string) $this->quantity_received);
    }
}
