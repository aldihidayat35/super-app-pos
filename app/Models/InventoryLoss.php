<?php

namespace App\Models;

use App\Enums\InventoryLossStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property InventoryLossStatus $status */
class InventoryLoss extends Model
{
    protected $fillable = [
        'number', 'work_location_id', 'warehouse_location_id', 'product_id', 'reported_by', 'approved_by',
        'loss_type', 'disposition', 'status', 'quantity', 'unit_cost_snapshot', 'loss_value',
        'reference_type', 'reference_id', 'reference_no', 'evidence_path', 'reason', 'reported_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryLossStatus::class,
            'quantity' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:2',
            'loss_value' => 'decimal:2',
            'reported_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
