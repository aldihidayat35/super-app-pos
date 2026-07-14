<?php

namespace App\Models;

use App\Enums\B2bOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bOrder extends Model
{
    protected $fillable = ['number', 'customer_id', 'requested_by', 'approved_by', 'customer_address_id', 'status', 'requested_delivery_date', 'delivery_method', 'courier_name', 'payment_preference', 'terms_accepted', 'subtotal_amount', 'discount_amount', 'tax_amount', 'shipping_cost_amount', 'grand_total_amount', 'credit_limit_snapshot', 'receivable_balance_snapshot', 'notes', 'idempotency_key', 'submitted_at', 'approved_at', 'cancelled_at', 'reservation_expires_at', 'packed_at', 'shipped_at', 'received_at', 'completed_at', 'rejected_at', 'cancel_reason', 'reject_reason', 'internal_note'];

    protected function casts(): array
    {
        return [
            'status' => B2bOrderStatus::class,
            'requested_delivery_date' => 'date',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_cost_amount' => 'decimal:2',
            'grand_total_amount' => 'decimal:2',
            'credit_limit_snapshot' => 'decimal:2',
            'receivable_balance_snapshot' => 'decimal:2',
            'terms_accepted' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'packed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<CustomerAddress, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    /** @return HasMany<B2bOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(B2bOrderItem::class);
    }

    /** @return HasMany<StockReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /** @return HasMany<B2bOrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(B2bOrderStatusHistory::class);
    }

    /** @return HasMany<B2bOrderMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(B2bOrderMessage::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
