<?php

namespace App\Models;

use App\Enums\WarehouseLocationType;
use Database\Factories\WarehouseLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseLocation extends Model
{
    /** @use HasFactory<WarehouseLocationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['warehouse_id', 'parent_id', 'type', 'code', 'full_code', 'name', 'capacity', 'item_type', 'is_active'];

    protected function casts(): array
    {
        return ['type' => WarehouseLocationType::class, 'capacity' => 'decimal:4', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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
}
