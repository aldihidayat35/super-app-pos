<?php

namespace App\Models;

use App\Enums\WarehouseLocationType;
use Database\Factories\WarehouseLocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseLocation extends Model
{
    /** @use HasFactory<WarehouseLocationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['warehouse_id', 'branch_id', 'parent_id', 'type', 'code', 'full_code', 'name', 'capacity', 'item_type', 'is_active'];

    protected function casts(): array
    {
        return ['type' => WarehouseLocationType::class, 'capacity' => 'decimal:4', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<WarehouseLocation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<WarehouseLocation, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ownerType(): string
    {
        return $this->branch_id !== null ? 'branch' : 'warehouse';
    }

    public function ownerName(): string
    {
        if ($this->branch !== null) {
            return $this->branch->name;
        }

        if ($this->warehouse !== null) {
            return $this->warehouse->name;
        }

        return '-';
    }

    public function ownerCode(): string
    {
        if ($this->branch !== null) {
            return $this->branch->code;
        }

        if ($this->warehouse !== null) {
            return $this->warehouse->code;
        }

        return '';
    }

    public function workLocationId(): ?int
    {
        if ($this->branch !== null) {
            return $this->branch->work_location_id;
        }

        if ($this->warehouse !== null) {
            return $this->warehouse->work_location_id;
        }

        return null;
    }

    /**
     * @param  Builder<WarehouseLocation>  $query
     * @param  list<int>  $workLocationIds
     * @return Builder<WarehouseLocation>
     */
    public function scopeForWorkLocationIds(Builder $query, array $workLocationIds): Builder
    {
        return $query->where(function (Builder $q) use ($workLocationIds): void {
            $q->whereHas('warehouse', fn (Builder $w) => $w->whereIn('work_location_id', $workLocationIds))
                ->orWhereHas('branch', fn (Builder $b) => $b->whereIn('work_location_id', $workLocationIds));
        });
    }

    /**
     * @param  Builder<WarehouseLocation>  $query
     * @return Builder<WarehouseLocation>
     */
    public function scopeForWorkLocation(Builder $query, int $workLocationId): Builder
    {
        return $query->where(function (Builder $q) use ($workLocationId): void {
            $q->whereHas('warehouse', fn (Builder $w) => $w->where('work_location_id', $workLocationId))
                ->orWhereHas('branch', fn (Builder $b) => $b->where('work_location_id', $workLocationId));
        });
    }
}
