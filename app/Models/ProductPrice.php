<?php

namespace App\Models;

use App\Enums\ProductPriceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $work_location_id
 * @property int|null $branch_id
 * @property string $channel
 * @property string $price_ring
 * @property string|null $min_price
 * @property string|null $recommended_price
 * @property string|null $max_price
 * @property string|null $minimum_qty
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property ProductPriceStatus $status
 * @property string|null $notes
 */
class ProductPrice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'branch_id', 'work_location_id', 'channel', 'price_ring', 'customer_category', 'min_price',
        'recommended_price', 'max_price', 'minimum_qty', 'priority', 'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'min_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'minimum_qty' => 'decimal:4',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => ProductPriceStatus::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
