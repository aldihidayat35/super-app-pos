<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $amount */
class FinancialMonthlyEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<FinancialMonthlyClosing, $this> */
    public function monthlyClosing(): BelongsTo
    {
        return $this->belongsTo(FinancialMonthlyClosing::class, 'financial_monthly_closing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
