<?php

namespace App\Services\Pricing;

use App\Enums\ProductPriceStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Support\Facades\DB;

/** Adapter kompatibilitas untuk pemanggil harga lokasi berbasis id. */
final class LocationPriceService
{
    public function __construct(private readonly LocationProductPriceService $prices) {}

    public function activePrice(int $productId, int $workLocationId, string $channel = 'retail'): ?ProductPrice
    {
        return ProductPrice::query()
            ->where('product_id', $productId)
            ->where(function ($query) use ($workLocationId): void {
                $query->where('work_location_id', $workLocationId)->orWhereNull('work_location_id');
            })
            ->where('channel', $channel)
            ->where('status', ProductPriceStatus::ACTIVE->value)
            ->orderBy('priority')
            ->orderByDesc('work_location_id')
            ->latest('id')
            ->first();
    }

    public function setLocationPrice(
        int $productId,
        int $workLocationId,
        string $channel,
        string $recommendedPrice,
        User $actor,
        ?string $minPrice = null,
        ?string $maxPrice = null,
        ?string $reason = null,
    ): ProductPrice {
        return DB::transaction(function () use ($productId, $workLocationId, $channel, $recommendedPrice, $actor, $minPrice, $maxPrice, $reason): ProductPrice {
            $product = Product::query()->findOrFail($productId);
            $location = WorkLocation::query()->findOrFail($workLocationId);
            $price = $this->prices->setActivePrice($product, $location, $recommendedPrice, $actor, 'manual', $reason);
            $price->forceFill([
                'channel' => $channel,
                'min_price' => $minPrice ?? $recommendedPrice,
                'max_price' => $maxPrice ?? $recommendedPrice,
            ])->save();

            return $price;
        });
    }

    public function copyPriceIfMissing(
        int $productId,
        int $fromWorkLocationId,
        int $toWorkLocationId,
        string $channel,
        User $actor,
    ): ?ProductPrice {
        $existing = $this->activePrice($productId, $toWorkLocationId, $channel);
        if ($existing?->work_location_id === $toWorkLocationId) {
            return null;
        }
        $source = $this->activePrice($productId, $fromWorkLocationId, $channel);
        if (! $source instanceof ProductPrice) {
            return null;
        }

        return $this->setLocationPrice(
            $productId,
            $toWorkLocationId,
            $channel,
            (string) $source->recommended_price,
            $actor,
            (string) $source->min_price,
            (string) $source->max_price,
            'Disalin dari lokasi asal saat penerimaan transfer.',
        );
    }
}
