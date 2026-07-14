<?php

namespace App\Models;

use App\Enums\B2bComplaintStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bComplaint extends Model
{
    protected $fillable = ['number', 'customer_id', 'b2b_order_id', 'shipment_id', 'b2b_order_item_id', 'type', 'requested_solution', 'quantity', 'status', 'evidence_path', 'message', 'resolution_note', 'created_by', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'status' => B2bComplaintStatus::class,
            'quantity' => 'decimal:4',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
