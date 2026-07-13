<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $fillable = ['product_id', 'work_location_id', 'warehouse_location_id', 'location_scope_key', 'quantity_on_hand', 'quantity_reserved', 'quantity_damaged', 'cost_value'];

    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:4', 'quantity_reserved' => 'decimal:4', 'quantity_damaged' => 'decimal:4', 'cost_value' => 'decimal:2'];
    }

    public function getAvailableQuantityAttribute(): string
    {
        return Decimal::sub(Decimal::sub((string) $this->quantity_on_hand, (string) $this->quantity_reserved), (string) $this->quantity_damaged);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    /** @return HasMany<StockMutation, $this> */
    public function mutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }
}
