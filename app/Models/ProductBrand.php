<?php

namespace App\Models;

use Database\Factories\ProductBrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBrand extends Model
{
    /** @use HasFactory<ProductBrandFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'description', 'logo_path', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
