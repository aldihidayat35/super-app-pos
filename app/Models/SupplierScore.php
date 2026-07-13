<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierScore extends Model
{
    protected $fillable = ['supplier_id', 'goods_receipt_id', 'quantity_received', 'quantity_accepted', 'quantity_rejected', 'quantity_damaged', 'quality_score', 'delivery_score', 'price_score', 'total_score', 'received_at'];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:4',
            'quantity_accepted' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'quality_score' => 'decimal:2',
            'delivery_score' => 'decimal:2',
            'price_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
