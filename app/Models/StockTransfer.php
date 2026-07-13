<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StockTransferStatus $status
 */
class StockTransfer extends Model
{
    protected $fillable = [
        'number', 'restock_request_id', 'source_work_location_id', 'source_warehouse_location_id',
        'destination_work_location_id', 'destination_warehouse_location_id', 'requested_by', 'approved_by',
        'picker_by', 'shipper_by', 'receiver_by', 'status', 'transfer_date', 'submitted_at', 'approved_at',
        'packing_started_at', 'shipped_at', 'received_at', 'completed_at', 'cancelled_at', 'carrier',
        'vehicle_number', 'tracking_number', 'shipping_cost', 'proof_path', 'notes', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'transfer_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'packing_started_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shipping_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<RestockRequest, $this> */
    public function restockRequest(): BelongsTo
    {
        return $this->belongsTo(RestockRequest::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function sourceWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'source_work_location_id');
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function destinationWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'destination_work_location_id');
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function sourceWarehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_warehouse_location_id');
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function destinationWarehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'destination_warehouse_location_id');
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

    /** @return BelongsTo<User, $this> */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_by');
    }

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** @return HasMany<StockTransferPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(StockTransferPackage::class);
    }

    /** @return HasMany<StockTransferReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransferReceipt::class);
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class, 'reference_id')->where('reference_type', 'stock_transfer');
    }

    /** @return HasMany<DocumentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class, 'document_id')->where('document_type', 'stock_transfer');
    }

    public function totalApprovedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, StockTransferItem $item): string => Decimal::add($carry, (string) $item->quantity_approved), '0.0000');
    }

    public function totalShippedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, StockTransferItem $item): string => Decimal::add($carry, (string) $item->quantity_shipped), '0.0000');
    }

    public function totalReceivedQuantity(): string
    {
        return $this->items->reduce(fn (string $carry, StockTransferItem $item): string => Decimal::add($carry, (string) $item->quantity_received), '0.0000');
    }
}
