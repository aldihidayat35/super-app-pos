<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'subcategory_id',
        'brand_id',
        'model',
        'size',
        'color',
        'material',
        'description',
        'base_unit_id',
        'status',
        'minimum_order',
        'minimum_stock',
        'safety_stock',
        'weight',
        'volume',
        'default_warehouse_id',
        'total_stock',
        'cost_price',
        'minimum_price',
        'main_image_path',
        'attributes',
        'has_transactions',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'minimum_order' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
        'safety_stock' => 'decimal:4',
        'weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'total_stock' => 'decimal:4',
        'cost_price' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'attributes' => 'array',
        'has_transactions' => 'boolean',
    ];

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'subcategory_id');
    }

    /** @return BelongsTo<ProductBrand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    /** @return HasMany<ProductUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** @return HasMany<ProductBarcode, $this> */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }
}
