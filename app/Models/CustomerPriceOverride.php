<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPriceOverride extends Model
{
    protected $fillable = [
        'customer_id', 'product_id', 'branch_id', 'channel', 'price', 'minimum_qty', 'discount_percent',
        'priority', 'status', 'starts_at', 'ends_at', 'is_active', 'notes', 'reason', 'requested_by',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_qty' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
