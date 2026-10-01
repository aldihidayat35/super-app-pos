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
        'tax_rule_id',
        'tax_category_code',
        'is_taxable',
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
        'is_taxable' => 'boolean',
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

    /** @return BelongsTo<TaxRule, $this> */
    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
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

    /**
     * Accessor for total_stock: computes from stock records in real-time.
     * Falls back to the stored column if stocks is already loaded.
     */
    public function getTotalStockAttribute(mixed $value): string
    {
        if ($this->relationLoaded('stocks')) {
            return (string) $this->stocks->sum('quantity_on_hand');
        }

        return (string) $this->getRawOriginal('total_stock');
    }

    /** @return HasMany<ProductPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class);
    }

    /** @return HasMany<PriceHistory, $this> */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /** @return HasMany<SupplierProduct, $this> */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public const DEFAULT_IMAGE_PATH = 'assets/media/products/default-product.png';

    public static function defaultImageUrl(): string
    {
        return '/'.ltrim(self::DEFAULT_IMAGE_PATH, '/');
    }

    public function hasCustomImage(): bool
    {
        if (filled($this->main_image_path)) {
            return true;
        }

        if ($this->relationLoaded('images')) {
            return $this->images->isNotEmpty();
        }

        return $this->images()->exists();
    }

    /**
     * Get the full URL for the main image, or default fallback image if not yet uploaded.
     */
    public function getMainImageUrlAttribute(): string
    {
        $path = $this->main_image_path;
        if (! $path) {
            $image = $this->relationLoaded('images')
                ? ($this->images->firstWhere('is_primary', true) ?? $this->images->sortBy('sort_order')->first())
                : ($this->images()->where('is_primary', true)->first() ?? $this->images()->orderBy('sort_order')->first());
            $path = $image?->path;
        }

        if (! $path) {
            return self::defaultImageUrl();
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        // Relative URL follows the host currently used by the cashier (Laragon, artisan serve, or production).
        return '/storage/'.ltrim($path, '/');
    }
}
