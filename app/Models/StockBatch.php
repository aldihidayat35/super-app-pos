<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBatch extends Model
{
    protected $fillable = [
        'source_batch_id', 'product_id', 'supplier_id', 'stock_id', 'work_location_id', 'warehouse_location_id',
        'goods_receipt_id', 'goods_receipt_item_id', 'source_type', 'source_id', 'source_item_id', 'unit_id',
        'conversion_factor_snapshot', 'batch_no', 'received_at', 'expires_at', 'cost_price',
        'entry_sale_price_snapshot', 'quantity_on_hand', 'quantity_reserved', 'quantity_damaged', 'status',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date', 'expires_at' => 'date', 'cost_price' => 'decimal:2',
            'entry_sale_price_snapshot' => 'decimal:2', 'conversion_factor_snapshot' => 'decimal:6',
            'quantity_on_hand' => 'decimal:4', 'quantity_reserved' => 'decimal:4', 'quantity_damaged' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<self, $this> */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_batch_id');
    }

    /** @return HasMany<self, $this> */
    public function childBatches(): HasMany
    {
        return $this->hasMany(self::class, 'source_batch_id');
    }

    /** @return HasMany<StockLayerAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(StockLayerAllocation::class);
    }

    public function availableQuantity(): string
    {
        return Decimal::sub(
            Decimal::sub((string) $this->quantity_on_hand, (string) $this->quantity_reserved),
            (string) $this->quantity_damaged,
        );
    }

    /** Nilai persediaan: HPP × qty_on_hand. */
    public function totalValue(): string
    {
        return Decimal::mul((string) $this->cost_price, (string) $this->quantity_on_hand, rightScale: 4);
    }

    /**
     * Batch aktif dengan ketersediaan stok > 0 (on_hand - reserved - damaged > 0).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereRaw('(quantity_on_hand - quantity_reserved - quantity_damaged) > 0');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtLocation(Builder $query, int $workLocationId, ?int $warehouseLocationId = null): Builder
    {
        $query->where('work_location_id', $workLocationId);

        if ($warehouseLocationId !== null) {
            $query->where('warehouse_location_id', $warehouseLocationId);
        }

        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
