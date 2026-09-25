<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $work_location_id
 * @property int $created_by
 * @property string $number
 * @property string $status
 * @property string $purpose
 * @property string|null $notes
 * @property string|null $decision_note
 * @property WorkLocation $workLocation
 * @property User $creator
 * @property User|null $approver
 * @property Collection<int, StockDirectAdjustmentItem> $items
 */
class StockDirectAdjustment extends Model
{
    protected $fillable = ['number', 'work_location_id', 'purpose', 'created_by', 'submitted_by', 'approved_by', 'rejected_by', 'cancelled_by', 'status', 'notes', 'decision_note', 'submitted_at', 'approved_at', 'rejected_at', 'cancelled_at', 'completed_at'];

    public function isOpeningBalance(): bool
    {
        return $this->purpose === 'opening_balance';
    }

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
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

    /** @return HasMany<StockDirectAdjustmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockDirectAdjustmentItem::class);
    }

    /** @return HasMany<StockDirectAdjustmentApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(StockDirectAdjustmentApproval::class);
    }
}
