<?php

namespace App\Models;

use App\Enums\StockOpnameStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property StockOpnameStatus $status
 */
class StockOpname extends Model
{
    protected $fillable = [
        'number', 'work_location_id', 'warehouse_location_id', 'category_id', 'pic_user_id', 'created_by',
        'approved_by', 'rejected_by', 'status', 'method', 'freeze_stock', 'blind_count', 'requires_owner_approval',
        'scheduled_at', 'started_at', 'submitted_at', 'approved_at', 'rejected_at', 'completed_at',
        'threshold_qty', 'threshold_value', 'total_difference_qty', 'total_difference_value', 'notes', 'reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockOpnameStatus::class,
            'freeze_stock' => 'boolean',
            'blind_count' => 'boolean',
            'requires_owner_approval' => 'boolean',
            'scheduled_at' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'threshold_qty' => 'decimal:4',
            'threshold_value' => 'decimal:2',
            'total_difference_qty' => 'decimal:4',
            'total_difference_value' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
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

    /** @return HasMany<StockOpnameItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /** @return HasMany<StockOpnameApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(StockOpnameApproval::class);
    }

    /** @return HasMany<DocumentStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class, 'document_id')->where('document_type', 'stock_opname');
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class, 'reference_id')->where('reference_type', 'stock_opname');
    }

    public function countedProgress(): string
    {
        $total = $this->items->count();
        if ($total === 0) {
            return '0%';
        }

        $counted = $this->items->filter(fn (StockOpnameItem $item): bool => $item->counted_qty !== null)->count();

        return round(($counted / $total) * 100).'%';
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('items');
        $qty = $this->items->reduce(fn (string $carry, StockOpnameItem $item): string => Decimal::add($carry, (string) $item->difference_qty), '0.0000');
        $value = $this->items->reduce(fn (string $carry, StockOpnameItem $item): string => Decimal::add($carry, (string) $item->estimated_value, 2), '0.00');

        $this->forceFill(['total_difference_qty' => $qty, 'total_difference_value' => $value])->save();
    }
}
