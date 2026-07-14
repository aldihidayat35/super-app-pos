<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = ['number', 'b2b_order_id', 'customer_id', 'origin_work_location_id', 'destination_address_id', 'status', 'delivery_method', 'courier_name', 'driver_name', 'vehicle_no', 'tracking_no', 'scheduled_date', 'shipped_at', 'delivered_at', 'failed_at', 'shipping_cost_amount', 'receiver_name', 'delivery_note', 'failure_reason', 'created_by', 'shipped_by', 'metadata'];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'scheduled_date' => 'date',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'shipping_cost_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerAddress, $this> */
    public function destinationAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'destination_address_id');
    }

    /** @return HasMany<ShipmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /** @return HasMany<ShipmentProof, $this> */
    public function proofs(): HasMany
    {
        return $this->hasMany(ShipmentProof::class);
    }
}
