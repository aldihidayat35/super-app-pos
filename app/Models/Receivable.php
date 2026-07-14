<?php

namespace App\Models;

use App\Enums\ReceivableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receivable extends Model
{
    protected $fillable = ['number', 'customer_id', 'work_location_id', 'invoice_id', 'pos_sale_id', 'source_type', 'source_id', 'source_no', 'channel', 'issue_date', 'due_date', 'principal_amount', 'adjustment_amount', 'paid_amount', 'outstanding_amount', 'aging_bucket', 'status', 'metadata'];

    protected function casts(): array
    {
        return [
            'status' => ReceivableStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<PosSale, $this> */
    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class);
    }

    /** @return HasMany<ReceivableEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ReceivableEntry::class);
    }

    /** @return HasMany<CollectionNote, $this> */
    public function collectionNotes(): HasMany
    {
        return $this->hasMany(CollectionNote::class);
    }
}
