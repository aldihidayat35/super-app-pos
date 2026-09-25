<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $work_location_id
 * @property bool $is_active
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property WorkLocation $workLocation
 */
class StockAdjustmentAccess extends Model
{
    protected $fillable = ['work_location_id', 'is_active', 'active_from', 'active_until', 'reason', 'activated_by', 'activated_at', 'deactivated_by', 'deactivated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'active_from' => 'datetime', 'active_until' => 'datetime', 'activated_at' => 'datetime', 'deactivated_at' => 'datetime'];
    }

    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && $this->active_from?->lte(now())
            && $this->active_until?->gt(now());
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return HasMany<StockAdjustmentAccessHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(StockAdjustmentAccessHistory::class);
    }
}
