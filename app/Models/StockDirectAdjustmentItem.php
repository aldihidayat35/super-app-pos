<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_direct_adjustment_id
 * @property int $product_id
 * @property int|null $stock_id
 * @property string $system_qty_snapshot
 * @property string $reserved_qty_snapshot
 * @property string $damaged_qty_snapshot
 * @property string $physical_qty
 * @property string $unit_cost_snapshot
 * @property string $note
 * @property Product $product
 * @property WarehouseLocation|null $warehouseLocation
 */
class StockDirectAdjustmentItem extends Model
{
    protected $fillable = ['stock_direct_adjustment_id', 'product_id', 'stock_id', 'warehouse_location_id', 'product_sku_snapshot', 'product_name_snapshot', 'system_qty_snapshot', 'physical_qty', 'difference_qty', 'reserved_qty_snapshot', 'damaged_qty_snapshot', 'unit_cost_snapshot', 'sale_price_snapshot', 'selected_stock_batch_ids', 'difference_value', 'posted_qty_before', 'posted_qty_after', 'note'];

    protected function casts(): array
    {
        return ['system_qty_snapshot' => 'decimal:4', 'physical_qty' => 'decimal:4', 'difference_qty' => 'decimal:4', 'reserved_qty_snapshot' => 'decimal:4', 'damaged_qty_snapshot' => 'decimal:4', 'unit_cost_snapshot' => 'decimal:2', 'sale_price_snapshot' => 'decimal:2', 'selected_stock_batch_ids' => 'array', 'difference_value' => 'decimal:2', 'posted_qty_before' => 'decimal:4', 'posted_qty_after' => 'decimal:4'];
    }

    /** @return BelongsTo<StockDirectAdjustment, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockDirectAdjustment::class, 'stock_direct_adjustment_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }
}
