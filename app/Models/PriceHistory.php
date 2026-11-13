<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $work_location_id
 * @property numeric-string|null $old_price
 * @property numeric-string $new_price
 * @property numeric-string|null $hpp_snapshot
 * @property numeric-string|null $minimum_price_snapshot
 * @property int|null $changed_by
 * @property string|null $source
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property-read Product|null $product
 * @property-read User|null $changer
 * @property-read WorkLocation|null $workLocation
 */
class PriceHistory extends Model
{
    protected $fillable = [
        'priceable_type', 'priceable_id', 'product_id', 'customer_id', 'branch_id', 'work_location_id', 'channel', 'price_ring',
        'old_price', 'new_price', 'hpp_snapshot', 'minimum_price_snapshot', 'changed_by', 'source',
        'bulk_batch_id', 'reason', 'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'hpp_snapshot' => 'decimal:2',
            'minimum_price_snapshot' => 'decimal:2',
            'effective_at' => 'date',
        ];
    }

    public function trend(): string
    {
        if ($this->old_price === null || Decimal::compare((string) $this->old_price, '0.00', 2) === 0) {
            return 'initial';
        }

        $cmp = Decimal::compare((string) $this->new_price, (string) $this->old_price, 2);
        if ($cmp > 0) {
            return 'up';
        }
        if ($cmp < 0) {
            return 'down';
        }

        return 'same';
    }

    public function priceDifference(): ?string
    {
        if ($this->old_price === null) {
            return null;
        }

        return Decimal::sub((string) $this->new_price, (string) $this->old_price, 2);
    }

    public function percentageDifference(): ?float
    {
        if ($this->old_price === null || Decimal::compare((string) $this->old_price, '0.00', 2) === 0) {
            return null;
        }

        $diff = (float) $this->priceDifference();
        $old = (float) $this->old_price;

        return $old > 0 ? ($diff / $old) * 100 : null;
    }

    public function trendLabel(): string
    {
        return match ($this->trend()) {
            'up' => 'Naik',
            'down' => 'Turun',
            'initial' => 'Harga Baru',
            default => 'Tetap',
        };
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return BelongsTo<WorkLocation, $this> */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }
}
