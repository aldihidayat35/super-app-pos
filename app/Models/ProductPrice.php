<?php

namespace App\Models;

use App\Enums\ProductPriceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property ProductPriceStatus $status */
class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'channel', 'price_ring', 'customer_category', 'min_price',
        'recommended_price', 'max_price', 'minimum_qty', 'priority', 'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'minimum_qty' => 'decimal:4',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => ProductPriceStatus::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
