<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferReceiptItem extends Model
{
    protected $fillable = ['stock_transfer_receipt_id', 'stock_transfer_item_id', 'quantity_received', 'quantity_damaged', 'quantity_discrepancy', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:4',
            'quantity_damaged' => 'decimal:4',
            'quantity_discrepancy' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<StockTransferReceipt, $this> */
    public function stockTransferReceipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class);
    }

    /** @return BelongsTo<StockTransferItem, $this> */
    public function stockTransferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class);
    }
}
