<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property GoodsReceiptStatus $status
 */
class GoodsReceipt extends Model
{
    protected $fillable = ['number', 'purchase_order_id', 'warehouse_id', 'supplier_id', 'received_at', 'delivery_note_number', 'received_by', 'status', 'posted_at', 'posted_by', 'actual_freight_cost', 'actual_additional_cost', 'notes', 'proof_path', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'received_at' => 'date',
            'posted_at' => 'datetime',
            'actual_freight_cost' => 'decimal:2',
            'actual_additional_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return HasMany<ProductCostHistory, $this> */
    public function costHistories(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class);
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class, 'reference_id')->where('reference_type', 'goods_receipt');
    }

    public function acceptedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, GoodsReceiptItem $item): string => Decimal::add($carry, (string) $item->quantity_accepted), '0.0000');
    }

    public function rejectedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, GoodsReceiptItem $item): string => Decimal::add($carry, (string) $item->quantity_rejected), '0.0000');
    }

    public function damagedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, GoodsReceiptItem $item): string => Decimal::add($carry, (string) $item->quantity_damaged), '0.0000');
    }
}
