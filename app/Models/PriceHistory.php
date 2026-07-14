<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = [
        'priceable_type', 'priceable_id', 'product_id', 'customer_id', 'branch_id', 'channel', 'price_ring',
        'old_price', 'new_price', 'hpp_snapshot', 'minimum_price_snapshot', 'changed_by', 'source',
        'bulk_batch_id', 'reason', 'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'hpp_snapshot' => 'decimal:2',
            'minimum_price_snapshot' => 'decimal:2',
            'effective_at' => 'date',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
