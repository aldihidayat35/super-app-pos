<?php

namespace App\Models;

use App\Enums\TaxDirection;
use App\Enums\TaxDocumentStatus;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TaxDirection $direction
 * @property TaxType $tax_type
 * @property TaxDocumentStatus $status
 * @property Carbon $tax_date
 */
class TaxDocument extends Model
{
    protected $fillable = [
        'tax_period_id', 'tax_rule_id', 'original_document_id', 'work_location_id', 'direction', 'tax_type',
        'document_type', 'document_number', 'source_key', 'source_type', 'source_id', 'counterparty_type',
        'counterparty_id', 'counterparty_name', 'counterparty_tax_number', 'counterparty_address',
        'issue_date', 'tax_date', 'dpp_amount', 'tax_rate', 'dpp_factor', 'tax_amount', 'luxury_tax_amount',
        'withholding_tax_amount', 'total_amount', 'is_creditable', 'status', 'reconciliation_status',
        'coretax_reference', 'reconciliation_notes', 'notes', 'metadata', 'created_by', 'posted_by',
        'reconciled_by', 'reversed_by', 'posted_at', 'reconciled_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TaxDirection::class,
            'tax_type' => TaxType::class,
            'issue_date' => 'date',
            'tax_date' => 'date',
            'dpp_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'dpp_factor' => 'decimal:8',
            'tax_amount' => 'decimal:2',
            'luxury_tax_amount' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'is_creditable' => 'boolean',
            'status' => TaxDocumentStatus::class,
            'metadata' => 'array',
            'posted_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaxPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(TaxPeriod::class, 'tax_period_id');
    }

    /** @return BelongsTo<TaxRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'tax_rule_id');
    }

    /** @return BelongsTo<TaxDocument, $this> */
    public function originalDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_document_id');
    }

    /** @return HasMany<TaxDocumentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TaxDocumentItem::class);
    }
}
