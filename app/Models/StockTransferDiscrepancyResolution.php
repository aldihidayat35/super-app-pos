<?php

namespace App\Models;

use App\Enums\StockTransferDiscrepancyResolutionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferDiscrepancyResolution extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'stock_transfer_item_id', 'quantity', 'resolution_type', 'notes', 'proof_path',
        'resolved_by', 'resolved_at', 'inventory_loss_id', 'idempotency_key', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'resolution_type' => StockTransferDiscrepancyResolutionType::class,
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** @return BelongsTo<StockTransferItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<InventoryLoss, $this> */
    public function inventoryLoss(): BelongsTo
    {
        return $this->belongsTo(InventoryLoss::class);
    }
}
