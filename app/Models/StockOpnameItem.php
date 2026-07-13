<?php

namespace App\Models;

use App\Enums\StockOpnameReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'stock_id', 'product_id', 'warehouse_location_id', 'counter_user_id', 'locked_by',
        'product_sku_snapshot', 'product_name_snapshot', 'system_qty_snapshot', 'counted_qty', 'difference_qty',
        'unit_cost', 'estimated_value', 'reason', 'note', 'evidence_path', 'has_transaction_after_snapshot',
        'counted_at', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'system_qty_snapshot' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'difference_qty' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'reason' => StockOpnameReason::class,
            'has_transaction_after_snapshot' => 'boolean',
            'counted_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StockOpname, $this> */
    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    /** @return BelongsTo<Stock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
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

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counter_user_id');
    }

    /** @return HasMany<StockOpnameCount, $this> */
    public function counts(): HasMany
    {
        return $this->hasMany(StockOpnameCount::class);
    }

    public function reasonEnum(): ?StockOpnameReason
    {
        $reason = $this->getAttribute('reason');

        if ($reason instanceof StockOpnameReason) {
            return $reason;
        }

        if (is_string($reason)) {
            return StockOpnameReason::tryFrom($reason);
        }

        return null;
    }
}
