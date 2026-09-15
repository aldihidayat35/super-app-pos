<?php

namespace App\Models;

use App\Enums\FinancialMonthStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property FinancialMonthStatus $status
 * @property int $month
 * @property string $auto_pos_sales_amount
 * @property string $auto_b2b_sales_amount
 * @property string $auto_returns_amount
 * @property string $auto_pos_cogs_amount
 * @property string $auto_b2b_cogs_amount
 * @property string $auto_shift_expense_amount
 * @property string $auto_receivable_balance
 * @property string $auto_inventory_balance
 * @property int $missing_hpp_count
 * @property FinancialYear $financialYear
 * @property Collection<int, FinancialMonthlyEntry> $entries
 */
class FinancialMonthlyClosing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => FinancialMonthStatus::class, 'source_snapshot' => 'array',
            'auto_pos_sales_amount' => 'decimal:2', 'auto_b2b_sales_amount' => 'decimal:2',
            'auto_returns_amount' => 'decimal:2', 'auto_pos_cogs_amount' => 'decimal:2',
            'auto_b2b_cogs_amount' => 'decimal:2', 'auto_shift_expense_amount' => 'decimal:2',
            'auto_receivable_balance' => 'decimal:2', 'auto_inventory_balance' => 'decimal:2',
            'submitted_at' => 'datetime', 'locked_at' => 'datetime', 'reopened_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FinancialYear, $this> */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    /** @return HasMany<FinancialMonthlyEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(FinancialMonthlyEntry::class);
    }
}
