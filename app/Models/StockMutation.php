<?php

namespace App\Models;

use App\Enums\StockMutationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    protected $fillable = [
        'product_id', 'stock_id', 'work_location_id', 'warehouse_location_id', 'mutation_type', 'direction',
        'quantity_on_hand_before', 'quantity_on_hand_change', 'quantity_on_hand_after',
        'quantity_reserved_before', 'quantity_reserved_change', 'quantity_reserved_after',
        'quantity_damaged_before', 'quantity_damaged_change', 'quantity_damaged_after',
        'unit_id', 'reference_type', 'reference_id', 'reference_no',
        'source_work_location_id', 'source_warehouse_location_id', 'destination_work_location_id', 'destination_warehouse_location_id',
        'actor_user_id', 'reason', 'idempotency_key', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'mutation_type' => StockMutationType::class,
            'quantity_on_hand_before' => 'decimal:4',
            'quantity_on_hand_change' => 'decimal:4',
            'quantity_on_hand_after' => 'decimal:4',
            'quantity_reserved_before' => 'decimal:4',
            'quantity_reserved_change' => 'decimal:4',
            'quantity_reserved_after' => 'decimal:4',
            'quantity_damaged_before' => 'decimal:4',
            'quantity_damaged_change' => 'decimal:4',
            'quantity_damaged_after' => 'decimal:4',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
