<?php

namespace App\Models\Concerns;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;

trait LocksProductUnitOnTransaction
{
    protected static function bootLocksProductUnitOnTransaction(): void
    {
        static::created(function (self $item): void {
            $productId = (int) $item->getAttribute('product_id');
            $unitId = (int) $item->getAttribute('unit_id');
            if ($productId <= 0 || $unitId <= 0) {
                return;
            }

            ProductUnit::query()->where('product_id', $productId)->where('unit_id', $unitId)->update(['is_locked' => true]);
            Product::query()->whereKey($productId)->update(['has_transactions' => true]);
            Unit::query()->whereKey($unitId)->update(['has_transactions' => true]);
        });
    }
}
