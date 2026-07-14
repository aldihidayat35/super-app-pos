<?php

namespace App\Models;

use App\Enums\PosHoldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PosHoldStatus $status */
class PosHold extends Model
{
    protected $fillable = [
        'number', 'branch_id', 'work_location_id', 'cash_shift_id', 'cashier_user_id', 'customer_id', 'status',
        'cart_snapshot', 'estimated_total', 'notes', 'cancel_reason', 'resumed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosHoldStatus::class,
            'cart_snapshot' => 'array',
            'estimated_total' => 'decimal:2',
            'resumed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<CashShift, $this> */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
