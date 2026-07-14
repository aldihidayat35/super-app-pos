<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderMessage extends Model
{
    protected $fillable = ['b2b_order_id', 'user_id', 'visibility', 'message'];

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
