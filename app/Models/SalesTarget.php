<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesTarget extends Model
{
    protected $fillable = ['sales_user_id', 'month', 'year', 'target_amount', 'bonus_percentage', 'created_by'];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'target_amount' => 'decimal:2',
            'bonus_percentage' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<SalesBonus, $this> */
    public function bonus(): HasOne
    {
        return $this->hasOne(SalesBonus::class);
    }
}
