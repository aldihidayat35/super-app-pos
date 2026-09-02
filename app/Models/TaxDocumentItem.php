<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDocumentItem extends Model
{
    protected $fillable = [
        'tax_document_id', 'product_id', 'product_code', 'description', 'unit_name', 'quantity', 'unit_price',
        'discount_amount', 'dpp_amount', 'tax_rate', 'tax_amount', 'luxury_tax_amount', 'line_total', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'dpp_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'luxury_tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<TaxDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class, 'tax_document_id');
    }
}
