<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentAccessHistory extends Model
{
    protected $fillable = ['stock_adjustment_access_id', 'work_location_id', 'action', 'is_active', 'active_from', 'active_until', 'reason', 'actor_user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'active_from' => 'datetime', 'active_until' => 'datetime', 'occurred_at' => 'datetime'];
    }
}
