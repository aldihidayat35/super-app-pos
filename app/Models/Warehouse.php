<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'work_location_id',
        'code',
        'name',
        'address',
        'city',
        'phone_number',
        'manager_user_id',
        'capacity',
        'service_area',
        'is_active',
        'has_transactions',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:4',
            'is_active' => 'boolean',
            'has_transactions' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * Get all users with kepala_gudang role assigned to this warehouse's work location.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function warehouseHeads(): BelongsToMany
    {
        return $this->workLocation()->users()
            ->whereHasPivot('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'kepala_gudang'));
    }

    /** @return HasMany<WarehouseLocation, $this> */
    public function warehouseLocations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'primary_warehouse_id');
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
