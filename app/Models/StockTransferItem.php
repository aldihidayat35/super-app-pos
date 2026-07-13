<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'restock_request_item_id', 'product_id', 'unit_id', 'source_warehouse_location_id',
        'destination_warehouse_location_id', 'product_sku_snapshot', 'product_name_snapshot', 'unit_name_snapshot',
        'conversion_factor_snapshot', 'quantity_requested', 'quantity_approved', 'quantity_reserved',
        'quantity_picked', 'quantity_short', 'quantity_shipped', 'quantity_received', 'quantity_damaged',
        'quantity_discrepancy', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'quantity_requested' => 'decimal:4',
            'quantity_approved' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'quantity_picked' => 'decimal:4',
            'quantity_short' => 'decimal:4',
            'quantity_shipped' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'quantity_discrepancy' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** @return BelongsTo<RestockRequestItem, $this> */
    public function restockRequestItem(): BelongsTo
    {
        return $this->belongsTo(RestockRequestItem::class);
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
    public function sourceWarehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_warehouse_location_id');
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function destinationWarehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'destination_warehouse_location_id');
    }

    /** @return HasMany<StockTransferReceiptItem, $this> */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(StockTransferReceiptItem::class);
    }

    public function inTransitQuantity(): string
    {
        return Decimal::sub(
            (string) $this->quantity_shipped,
            Decimal::add(Decimal::add((string) $this->quantity_received, (string) $this->quantity_damaged), (string) $this->quantity_discrepancy),
        );
    }
}
