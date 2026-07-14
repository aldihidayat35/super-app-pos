<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bCartItem extends Model
{
    protected $fillable = ['b2b_cart_id', 'product_id', 'unit_id', 'quantity', 'base_quantity', 'price_snapshot', 'line_total', 'price_source', 'availability_snapshot', 'price_metadata', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'price_snapshot' => 'decimal:2',
            'line_total' => 'decimal:2',
            'price_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<B2bCart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(B2bCart::class, 'b2b_cart_id');
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
}
