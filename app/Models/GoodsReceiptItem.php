<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'purchase_order_item_id', 'product_id', 'unit_id', 'warehouse_location_id',
        'product_sku_snapshot', 'product_name_snapshot', 'unit_name_snapshot', 'conversion_factor_snapshot',
        'quantity_ordered', 'previously_received', 'outstanding_before', 'quantity_received',
        'quantity_accepted', 'quantity_rejected', 'quantity_damaged', 'quantity_returned_to_supplier',
        'unit_price', 'landed_cost_allocated', 'batch_no', 'qc_notes', 'hpp_before', 'incoming_cost', 'hpp_after',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'quantity_ordered' => 'decimal:4',
            'previously_received' => 'decimal:4',
            'outstanding_before' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_accepted' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'quantity_returned_to_supplier' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'landed_cost_allocated' => 'decimal:2',
            'hpp_before' => 'decimal:2',
            'incoming_cost' => 'decimal:2',
            'hpp_after' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    /** @return HasMany<ReceiptQcResult, $this> */
    public function qcResults(): HasMany
    {
        return $this->hasMany(ReceiptQcResult::class);
    }

    public function acceptedBaseQuantity(): string
    {
        return Decimal::mul((string) $this->quantity_accepted, (string) $this->conversion_factor_snapshot, 4, 6, 4);
    }

    public function damagedBaseQuantity(): string
    {
        return Decimal::mul((string) $this->quantity_damaged, (string) $this->conversion_factor_snapshot, 4, 6, 4);
    }
}
