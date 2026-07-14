<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionNote extends Model
{
    protected $fillable = ['customer_id', 'receivable_id', 'created_by', 'channel', 'contact_person', 'note', 'next_follow_up_date', 'delivery_status'];

    protected function casts(): array
    {
        return ['next_follow_up_date' => 'date'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Receivable, $this> */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }
}
