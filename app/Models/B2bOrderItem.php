<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bOrderItem extends Model
{
    protected $fillable = ['b2b_order_id', 'product_id', 'unit_id', 'sku_snapshot', 'product_name_snapshot', 'unit_name_snapshot', 'conversion_factor_snapshot', 'quantity', 'approved_quantity', 'base_quantity', 'reserved_quantity', 'issued_quantity', 'shortage_quantity', 'fulfillment_status', 'minimum_price_snapshot', 'selected_price', 'discount_amount', 'tax_amount', 'line_total', 'price_source', 'available_stock_snapshot', 'price_snapshot', 'notes'];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'quantity' => 'decimal:4',
            'approved_quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
            'shortage_quantity' => 'decimal:4',
            'minimum_price_snapshot' => 'decimal:2',
            'selected_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'available_stock_snapshot' => 'decimal:4',
            'price_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
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

    /** @return HasMany<StockReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }
}
