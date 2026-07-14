<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property CashShiftStatus $status */
class CashShift extends Model
{
    protected $fillable = [
        'number', 'branch_id', 'work_location_id', 'cashier_user_id', 'attendance_id', 'attendance_override_by', 'attendance_override_reason', 'opened_by', 'closed_by', 'status',
        'opening_cash_amount', 'expected_cash_amount', 'actual_cash_amount', 'terminal_code',
        'cash_sales_amount', 'non_cash_sales_amount', 'refund_amount', 'expense_amount', 'receivable_amount',
        'difference_amount', 'discrepancy_threshold_amount', 'opened_at', 'closing_submitted_at',
        'closed_at', 'closing_submitted_by', 'approved_by', 'approved_at', 'rejected_at',
        'discrepancy_reason', 'handover_notes', 'approval_notes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashShiftStatus::class,
            'opening_cash_amount' => 'decimal:2',
            'expected_cash_amount' => 'decimal:2',
            'actual_cash_amount' => 'decimal:2',
            'cash_sales_amount' => 'decimal:2',
            'non_cash_sales_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'expense_amount' => 'decimal:2',
            'receivable_amount' => 'decimal:2',
            'difference_amount' => 'decimal:2',
            'discrepancy_threshold_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closing_submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return HasMany<PosSale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    /** @return HasMany<ShiftExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(ShiftExpense::class);
    }

    /** @return HasMany<CashCount, $this> */
    public function cashCounts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }

    /** @return HasMany<ShiftApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(ShiftApproval::class);
    }
}
