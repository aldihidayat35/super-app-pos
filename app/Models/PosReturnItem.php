<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReturnItem extends Model
{
    protected $fillable = [
        'pos_return_id', 'pos_sale_item_id', 'product_id', 'warehouse_location_id', 'quantity', 'condition',
        'refund_amount', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'refund_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PosReturn, $this> */
    public function posReturn(): BelongsTo
    {
        return $this->belongsTo(PosReturn::class);
    }

    /** @return BelongsTo<PosSaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
