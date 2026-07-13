<?php

namespace App\Models;

use App\Enums\RestockRequestStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RestockRequestStatus $status
 */
class RestockRequest extends Model
{
    protected $fillable = ['number', 'branch_id', 'source_warehouse_id', 'requested_by', 'approved_by', 'status', 'priority', 'needed_at', 'submitted_at', 'approved_at', 'rejected_at', 'notes', 'reject_reason'];

    protected function casts(): array
    {
        return [
            'status' => RestockRequestStatus::class,
            'needed_at' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<RestockRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RestockRequestItem::class);
    }

    /** @return HasMany<StockTransfer, $this> */
    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    /** @return HasMany<DocumentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class, 'document_id')->where('document_type', 'restock_request');
    }

    public function requestedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, RestockRequestItem $item): string => Decimal::add($carry, (string) $item->quantity_requested), '0.0000');
    }
}
