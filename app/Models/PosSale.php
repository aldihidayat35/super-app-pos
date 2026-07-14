<?php

namespace App\Models;

use App\Enums\PosSaleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property PosSaleStatus $status */
class PosSale extends Model
{
    protected $fillable = [
        'number', 'branch_id', 'work_location_id', 'cash_shift_id', 'cashier_user_id', 'customer_id', 'status',
        'subtotal_amount', 'discount_amount', 'tax_amount', 'grand_total_amount', 'paid_amount', 'change_amount',
        'total_margin_amount', 'idempotency_key', 'completed_at', 'void_requested_by', 'void_approved_by',
        'voided_at', 'void_reason', 'receipt_print_count', 'last_printed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosSaleStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'total_margin_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
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

    /** @return HasMany<PosSaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    /** @return HasMany<SalePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /** @return HasMany<StockMutation, $this> */
    public function stockMutations(): HasMany
    {
        return $this->hasMany(StockMutation::class, 'reference_id')->where('reference_type', 'pos_sale');
    }

    /** @return HasMany<PosReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(PosReturn::class);
    }

    /** @return HasMany<Receivable, $this> */
    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }
}
