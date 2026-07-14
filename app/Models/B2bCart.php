<?php

namespace App\Models;

use App\Enums\B2bCartStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bCart extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'status', 'notes'];

    protected function casts(): array
    {
        return ['status' => B2bCartStatus::class];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<B2bCartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(B2bCartItem::class);
    }
}
