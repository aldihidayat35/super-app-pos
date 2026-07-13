<?php

namespace App\Models;

use App\Enums\ReceiptQcStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptQcResult extends Model
{
    protected $fillable = ['goods_receipt_item_id', 'qc_status', 'quantity', 'reason'];

    protected function casts(): array
    {
        return ['qc_status' => ReceiptQcStatus::class, 'quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<GoodsReceiptItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }
}
