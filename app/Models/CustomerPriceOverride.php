<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPriceOverride extends Model
{
    protected $fillable = ['customer_id', 'product_id', 'price', 'starts_at', 'ends_at', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'starts_at' => 'date', 'ends_at' => 'date', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
