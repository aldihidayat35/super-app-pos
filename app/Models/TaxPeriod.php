<?php

namespace App\Models;

use App\Enums\TaxPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property TaxPeriodStatus $status */
class TaxPeriod extends Model
{
    protected $fillable = [
        'month', 'year', 'status', 'output_dpp_amount', 'output_tax_amount', 'input_dpp_amount',
        'creditable_input_tax_amount', 'non_creditable_input_tax_amount', 'withholding_tax_amount',
        'compensation_amount', 'payable_amount', 'filing_reference', 'payment_reference',
        'reviewed_by', 'approved_by', 'reported_by', 'paid_by', 'locked_by', 'reopened_by',
        'reviewed_at', 'approved_at', 'reported_at', 'paid_at', 'locked_at', 'reopened_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'status' => TaxPeriodStatus::class,
            'output_dpp_amount' => 'decimal:2',
            'output_tax_amount' => 'decimal:2',
            'input_dpp_amount' => 'decimal:2',
            'creditable_input_tax_amount' => 'decimal:2',
            'non_creditable_input_tax_amount' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'compensation_amount' => 'decimal:2',
            'payable_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'reported_at' => 'datetime',
            'paid_at' => 'datetime',
            'locked_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /** @return HasMany<TaxDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TaxDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
