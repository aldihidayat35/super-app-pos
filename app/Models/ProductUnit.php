<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $conversion_factor
 */
class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'name', 'conversion_factor', 'is_base', 'is_sellable', 'is_active', 'is_locked'];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'is_base' => 'boolean',
        'is_sellable' => 'boolean',
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
    ];

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

    /** @return HasMany<ProductBarcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }
}
