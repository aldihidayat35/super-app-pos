<?php

namespace App\Models;

use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property ReturnStatus $status */
class ReturnDocument extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'number', 'work_location_id', 'source_type', 'source_id', 'source_name', 'destination_type', 'destination_id',
        'destination_name', 'reference_type', 'reference_id', 'reference_no', 'reason', 'requested_resolution',
        'status', 'requested_by', 'checker_user_id', 'approved_by', 'settled_by', 'return_date', 'submitted_at',
        'inspected_at', 'approved_at', 'settled_at', 'total_quantity', 'total_value', 'total_loss_value',
        'requires_approval', 'evidence_path', 'idempotency_key', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'requested_resolution' => ReturnResolution::class,
            'return_date' => 'date',
            'submitted_at' => 'datetime',
            'inspected_at' => 'datetime',
            'approved_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_quantity' => 'decimal:4',
            'total_value' => 'decimal:2',
            'total_loss_value' => 'decimal:2',
            'requires_approval' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    /** @return HasMany<ReturnInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(ReturnInspection::class, 'return_id');
    }

    /** @return HasMany<ReturnSettlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(ReturnSettlement::class, 'return_id');
    }

    /** @return HasMany<DocumentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class, 'document_id')->where('document_type', 'return');
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class, 'reference_id')->where('reference_type', 'return');
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('items');
        $quantity = $this->items->reduce(fn (string $carry, ReturnItem $item): string => Decimal::add($carry, (string) $item->quantity_requested), '0.0000');
        $value = $this->items->reduce(fn (string $carry, ReturnItem $item): string => Decimal::add($carry, (string) $item->line_value, 2), '0.00');
        $loss = $this->items->reduce(fn (string $carry, ReturnItem $item): string => Decimal::add($carry, (string) $item->loss_value, 2), '0.00');

        $this->forceFill(['total_quantity' => $quantity, 'total_value' => $value, 'total_loss_value' => $loss])->save();
    }

    public function requestedResolutionValue(): string
    {
        $resolution = $this->getAttribute('requested_resolution');

        if ($resolution instanceof ReturnResolution) {
            return $resolution->value;
        }

        return (string) $resolution;
    }
}
