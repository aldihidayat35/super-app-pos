<?php

namespace App\Models;

use App\Enums\PriceApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PriceApprovalStatus $status */
class PriceApprovalRequest extends Model
{
    protected $fillable = [
        'approval_type', 'document_type', 'document_id', 'product_id', 'customer_id', 'branch_id',
        'requested_by', 'approved_by', 'status', 'requested_price', 'minimum_price_snapshot',
        'maximum_price_snapshot', 'hpp_snapshot', 'discount_percent', 'reason', 'decision_notes',
        'expires_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PriceApprovalStatus::class,
            'requested_price' => 'decimal:2',
            'minimum_price_snapshot' => 'decimal:2',
            'maximum_price_snapshot' => 'decimal:2',
            'hpp_snapshot' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
