<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'work_location_id',
        'primary_warehouse_id',
        'code',
        'name',
        'address',
        'phone_number',
        'manager_user_id',
        'sales_target',
        'price_configuration',
        'closing_configuration',
        'is_closing_required',
        'is_active',
        'has_transactions',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sales_target' => 'decimal:2',
            'is_closing_required' => 'boolean',
            'is_active' => 'boolean',
            'has_transactions' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function primaryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'primary_warehouse_id');
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
