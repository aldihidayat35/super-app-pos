<?php

namespace App\Models;

use App\Enums\ReturnCondition;
use App\Enums\ReturnResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_id', 'product_id', 'unit_id', 'warehouse_location_id', 'source_item_type', 'source_item_id',
        'product_sku_snapshot', 'product_name_snapshot', 'unit_name_snapshot', 'conversion_factor_snapshot',
        'source_quantity', 'quantity_requested', 'quantity_accepted_good', 'quantity_accepted_damaged',
        'quantity_rejected', 'unit_cost_snapshot', 'line_value', 'loss_value', 'condition', 'reason',
        'resolution', 'notes', 'evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'source_quantity' => 'decimal:4',
            'quantity_requested' => 'decimal:4',
            'quantity_accepted_good' => 'decimal:4',
            'quantity_accepted_damaged' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:2',
            'line_value' => 'decimal:2',
            'loss_value' => 'decimal:2',
            'condition' => ReturnCondition::class,
            'resolution' => ReturnResolution::class,
        ];
    }

    /** @return BelongsTo<ReturnDocument, $this> */
    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(ReturnDocument::class, 'return_id');
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

    /** @return HasMany<ReturnInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(ReturnInspection::class);
    }
}
