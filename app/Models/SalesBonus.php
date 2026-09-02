<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBonus extends Model
{
    protected $fillable = ['sales_target_id', 'sales_user_id', 'month', 'year', 'sales_amount', 'order_count', 'target_amount', 'bonus_percentage', 'bonus_amount', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'sales_amount' => 'decimal:2',
            'order_count' => 'integer',
            'target_amount' => 'decimal:2',
            'bonus_percentage' => 'decimal:4',
            'bonus_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SalesTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(SalesTarget::class, 'sales_target_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
