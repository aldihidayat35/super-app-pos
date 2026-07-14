<?php

namespace App\Models;

use App\Enums\StockReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $fillable = ['b2b_order_id', 'b2b_order_item_id', 'product_id', 'stock_id', 'work_location_id', 'warehouse_location_id', 'quantity_reserved', 'quantity_released', 'quantity_issued', 'status', 'reserved_at', 'expires_at', 'released_at', 'issued_at', 'reserved_by', 'released_by', 'idempotency_key', 'reason', 'metadata'];

    protected function casts(): array
    {
        return [
            'quantity_reserved' => 'decimal:4',
            'quantity_released' => 'decimal:4',
            'quantity_issued' => 'decimal:4',
            'status' => StockReservationStatus::class,
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    /** @return BelongsTo<B2bOrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(B2bOrderItem::class, 'b2b_order_item_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Stock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }
}
