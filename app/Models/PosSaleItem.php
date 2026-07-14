<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSaleItem extends Model
{
    protected $fillable = [
        'pos_sale_id', 'product_id', 'unit_id', 'warehouse_location_id', 'sku_snapshot', 'product_name_snapshot',
        'unit_name_snapshot', 'conversion_factor_snapshot', 'quantity', 'base_quantity', 'hpp_snapshot',
        'minimum_price_snapshot', 'selected_price', 'discount_percent', 'discount_amount', 'tax_amount',
        'line_total', 'margin_amount', 'price_source', 'price_snapshot', 'returned_quantity',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'decimal:6',
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'hpp_snapshot' => 'decimal:2',
            'minimum_price_snapshot' => 'decimal:2',
            'selected_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'price_snapshot' => 'array',
            'returned_quantity' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
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

    /** @return HasMany<PosReturnItem, $this> */
    public function returnItems(): HasMany
    {
        return $this->hasMany(PosReturnItem::class);
    }
}
