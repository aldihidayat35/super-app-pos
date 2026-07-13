<?php

namespace App\Models;

use App\Enums\StockOpnameReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameCount extends Model
{
    protected $fillable = ['stock_opname_item_id', 'counter_user_id', 'counted_qty', 'reason', 'note', 'evidence_path', 'counted_at'];

    protected function casts(): array
    {
        return [
            'counted_qty' => 'decimal:4',
            'reason' => StockOpnameReason::class,
            'counted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StockOpnameItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StockOpnameItem::class, 'stock_opname_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counter_user_id');
    }
}
