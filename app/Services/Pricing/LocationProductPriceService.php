<?php

namespace App\Services\Pricing;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkLocation;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

final class LocationProductPriceService
{
    public function activePrice(Product $product, WorkLocation $location, string $channel = 'retail'): ?string
    {
        $price = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where(function ($query) use ($location): void {
                $query->where('work_location_id', $location->id)->orWhereNull('work_location_id');
            })
            ->where('channel', $channel)
            ->where('status', ProductPriceStatus::ACTIVE->value)
            ->orderByDesc('work_location_id')
            ->orderBy('priority')
            ->latest('id')
            ->value('recommended_price');

        return $price === null ? null : Decimal::normalize((string) $price, 2);
    }

    public function setActivePrice(
        Product $product,
        WorkLocation $location,
        string $amount,
        ?User $actor,
        string $source,
        ?string $reason = null,
    ): ProductPrice {
        $amount = Decimal::normalize($amount, 2);

        return DB::transaction(function () use ($product, $location, $amount, $actor, $source, $reason): ProductPrice {
            $current = ProductPrice::query()
                ->where('product_id', $product->id)
                ->where('work_location_id', $location->id)
                ->where('channel', 'retail')
                ->where('price_ring', 'retail')
                ->whereNull('customer_category')
                ->lockForUpdate()
                ->first();
            $old = $current?->recommended_price;

            $price = ProductPrice::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'work_location_id' => $location->id,
                    'channel' => 'retail',
                    'price_ring' => 'retail',
                    'customer_category' => null,
                ],
                [
                    'branch_id' => Branch::query()->where('work_location_id', $location->id)->value('id'),
                    'min_price' => $amount,
                    'recommended_price' => $amount,
                    'max_price' => $amount,
                    'minimum_qty' => 1,
                    'priority' => 10,
                    'status' => ProductPriceStatus::ACTIVE->value,
                    'notes' => $reason,
                ],
            );

            if ($old === null || Decimal::compare((string) $old, $amount, 2) !== 0) {
                PriceHistory::query()->create([
                    'priceable_type' => ProductPrice::class,
                    'priceable_id' => $price->id,
                    'product_id' => $product->id,
                    'branch_id' => $price->branch_id,
                    'work_location_id' => $location->id,
                    'channel' => 'retail',
                    'price_ring' => 'retail',
                    'old_price' => $old,
                    'new_price' => $amount,
                    'hpp_snapshot' => $product->cost_price,
                    'minimum_price_snapshot' => $amount,
                    'changed_by' => $actor?->id,
                    'source' => $source,
                    'reason' => $reason,
                ]);
            }

            return $price;
        });
    }
}
