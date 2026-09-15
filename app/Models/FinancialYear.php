<?php

namespace App\Models;

use App\Enums\FinancialYearStatus;
use App\Enums\IncomeTaxScheme;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property FinancialYearStatus $status
 * @property IncomeTaxScheme $tax_scheme
 * @property int $year
 * @property Collection<int, FinancialMonthlyClosing> $months
 */
class FinancialYear extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => FinancialYearStatus::class, 'tax_scheme' => IncomeTaxScheme::class,
            'is_historical' => 'boolean', 'tax_scheme_confirmed' => 'boolean',
            'final_rate' => 'decimal:4', 'facility_rate' => 'decimal:4', 'general_rate' => 'decimal:4',
            'small_turnover_limit' => 'decimal:2', 'facility_turnover_limit' => 'decimal:2',
            'fiscal_positive_adjustment' => 'decimal:2', 'fiscal_negative_adjustment' => 'decimal:2',
            'tax_credit_amount' => 'decimal:2', 'installment_amount' => 'decimal:2',
            'prior_payment_amount' => 'decimal:2', 'manual_tax_amount' => 'decimal:2',
            'commercial_profit_amount' => 'decimal:2', 'taxable_income_amount' => 'decimal:2',
            'income_tax_amount' => 'decimal:2', 'tax_payable_amount' => 'decimal:2',
            'balance_difference_amount' => 'decimal:2', 'reviewed_at' => 'datetime',
            'approved_at' => 'datetime', 'locked_at' => 'datetime', 'reopened_at' => 'datetime',
        ];
    }

    /** @return HasMany<FinancialMonthlyClosing, $this> */
    public function months(): HasMany
    {
        return $this->hasMany(FinancialMonthlyClosing::class)->orderBy('month');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
