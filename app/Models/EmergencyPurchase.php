<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyPurchase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total_cost' => 'decimal:2', 'supplier_refund_amount' => 'decimal:2',
            'reimbursement_amount' => 'decimal:2', 'reimbursed_at' => 'datetime',
            'purchased_at' => 'datetime', 'supplier_returned_at' => 'datetime',
            'regularized_at' => 'datetime'];
    }

    /** @return HasMany<EmergencyPurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EmergencyPurchaseItem::class);
    }

    /** @return HasMany<EmergencyPurchaseHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(EmergencyPurchaseHistory::class)->orderBy('id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<PosSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    /** @return BelongsTo<ShiftExpense, $this> */
    public function shiftExpense(): BelongsTo
    {
        return $this->belongsTo(ShiftExpense::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
