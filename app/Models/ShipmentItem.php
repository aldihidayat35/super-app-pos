<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    protected $fillable = ['shipment_id', 'b2b_order_item_id', 'product_id', 'quantity_planned', 'quantity_shipped', 'quantity_delivered', 'quantity_failed', 'status'];

    protected function casts(): array
    {
        return [
            'quantity_planned' => 'decimal:4',
            'quantity_shipped' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'quantity_failed' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<B2bOrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(B2bOrderItem::class, 'b2b_order_item_id');
    }
}
