<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLayerAllocation extends Model
{
    /** @var list<string> */
    public const EVENT_TYPES = [
        'sale', 'transfer_out', 'transfer_in', 'rack_move',
        'adjustment_positive', 'adjustment_negative', 'loss',
        'return', 'void', 'b2b_shipment', 'opening_stock', 'goods_receipt',
    ];

    protected $fillable = [
        'stock_batch_id', 'product_id', 'work_location_id', 'warehouse_location_id',
        'reference_type', 'reference_id', 'event_type', 'quantity', 'unit_cost',
        'sale_price_snapshot', 'actor_user_id', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'sale_price_snapshot' => 'decimal:2',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
