<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bOrderStatusHistory extends Model
{
    protected $fillable = ['b2b_order_id', 'from_status', 'to_status', 'actor_user_id', 'note', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<B2bOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(B2bOrder::class, 'b2b_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
