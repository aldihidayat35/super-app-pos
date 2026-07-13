<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferReceipt extends Model
{
    protected $fillable = ['stock_transfer_id', 'received_by', 'received_at', 'proof_path', 'notes', 'idempotency_key'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<StockTransferReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferReceiptItem::class);
    }
}
