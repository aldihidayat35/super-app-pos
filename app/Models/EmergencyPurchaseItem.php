<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyPurchaseItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:4', 'base_quantity' => 'decimal:4',
            'available_at_request' => 'decimal:4', 'shortage_at_request' => 'decimal:4',
            'purchased_quantity' => 'decimal:4', 'allocated_quantity' => 'decimal:4',
            'assigned_quantity' => 'decimal:4',
            'supplier_returned_quantity' => 'decimal:4', 'unit_cost' => 'decimal:2',
            'warehouse_quantity' => 'decimal:4',
            'regularized_quantity' => 'decimal:4',
            'expected_sale_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<EmergencyPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(EmergencyPurchase::class, 'emergency_purchase_id');
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

    /** @return HasMany<PosSaleAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PosSaleAllocation::class);
    }

    /** @return BelongsTo<EmergencyPurchaseItem, $this> */
    public function assignedSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'assigned_source_item_id');
    }

    /** @return HasMany<EmergencyPurchaseItem, $this> */
    public function assignedTargets(): HasMany
    {
        return $this->hasMany(self::class, 'assigned_source_item_id');
    }
}
