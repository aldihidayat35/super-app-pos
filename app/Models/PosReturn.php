<?php

namespace App\Models;

use App\Enums\PosReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property PosReturnStatus $status */
class PosReturn extends Model
{
    protected $fillable = [
        'number', 'pos_sale_id', 'branch_id', 'work_location_id', 'cashier_user_id', 'status', 'resolution',
        'refund_method', 'refund_amount', 'reason', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosReturnStatus::class,
            'refund_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    /** @return HasMany<PosReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PosReturnItem::class);
    }
}
