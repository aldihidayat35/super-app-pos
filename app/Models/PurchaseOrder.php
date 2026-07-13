<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property PurchaseOrderStatus $status
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'warehouse_id', 'supplier_id', 'purchase_request_id', 'order_date', 'expected_at', 'payment_term_days', 'notes', 'status',
        'created_by', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'sent_at', 'sent_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'items_subtotal', 'header_discount', 'freight_cost', 'additional_cost', 'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_at' => 'date',
            'payment_term_days' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'items_subtotal' => 'decimal:2',
            'header_discount' => 'decimal:2',
            'freight_cost' => 'decimal:2',
            'additional_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<GoodsReceipt, $this> */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /** @return HasMany<DocumentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class, 'document_id')->where('document_type', 'purchase_order');
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'document_id')->where('document_type', 'purchase_order');
    }

    public function receivedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, PurchaseOrderItem $item): string => Decimal::add($carry, (string) $item->quantity_received), '0.0000');
    }

    public function orderedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, PurchaseOrderItem $item): string => Decimal::add($carry, (string) $item->quantity_ordered), '0.0000');
    }

    public function outstandingQuantity(): string
    {
        return Decimal::sub($this->orderedQuantity(), $this->receivedQuantity());
    }
}
