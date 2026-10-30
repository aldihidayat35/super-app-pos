<?php

namespace App\Services\Pricing;

use App\Enums\ProductPriceStatus;
use App\Models\PriceHistory;
use App\Models\ProductPrice;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LocationPriceService
{
    /**
     * Ambil harga aktif produk di lokasi tertentu.
     *
     * Priority: work_location_id match → branch_id match → global (no branch, no location).
     * Channel default 'retail'.
     */
    public function activePrice(int $productId, int $workLocationId, string $channel = 'retail'): ?ProductPrice
    {
        $now = Carbon::today()->toDateString();

        $base = ProductPrice::query()
            ->where('product_id', $productId)
            ->where('channel', $channel)
            ->where('status', ProductPriceStatus::ACTIVE->value)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));

        // 1. Harga spesifik untuk lokasi kerja ini
        $byLocation = (clone $base)->where('work_location_id', $workLocationId)->first();
        if ($byLocation !== null) {
            return $byLocation;
        }

        // 2. Harga berdasarkan branch dari lokasi kerja
        /** @var WorkLocation|null $workLocation */
        $workLocation = WorkLocation::find($workLocationId);
        if ($workLocation !== null) {
            $branchId = $workLocation->branch_id ?? null;
            if ($branchId !== null) {
                $byBranch = (clone $base)
                    ->whereNull('work_location_id')
                    ->where('branch_id', $branchId)
                    ->first();

                if ($byBranch !== null) {
                    return $byBranch;
                }
            }
        }

        // 3. Harga global (tanpa branch, tanpa lokasi)
        return (clone $base)
            ->whereNull('work_location_id')
            ->whereNull('branch_id')
            ->first();
    }

    /**
     * Set harga baru di lokasi tertentu.
     *
     * - Nonaktifkan harga lokasi lama (status → inactive, ends_at = hari ini).
     * - Buat ProductPrice baru dengan work_location_id.
     * - Catat PriceHistory.
     * - Tidak mengubah harga di lokasi lain.
     *
     * @return ProductPrice harga baru yang aktif
     */
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
        return DB::transaction(function () use (
            $productId, $workLocationId, $channel, $recommendedPrice, $actor, $minPrice, $maxPrice, $reason
        ): ProductPrice {
            $today = Carbon::today()->toDateString();

            // Nonaktifkan harga lokasi yang sedang aktif
            $old = ProductPrice::query()
                ->where('product_id', $productId)
                ->where('work_location_id', $workLocationId)
                ->where('channel', $channel)
                ->where('status', ProductPriceStatus::ACTIVE->value)
                ->first();

            if ($old !== null) {
                $old->status  = ProductPriceStatus::INACTIVE;
                $old->ends_at = $today;
                $old->save();
            }

            /** @var ProductPrice $newPrice */
            $newPrice = ProductPrice::create([
                'product_id'         => $productId,
                'work_location_id'   => $workLocationId,
                'channel'            => $channel,
                'recommended_price'  => $recommendedPrice,
                'min_price'          => $minPrice,
                'max_price'          => $maxPrice,
                'status'             => ProductPriceStatus::ACTIVE,
                'starts_at'          => $today,
            ]);

            PriceHistory::create([
                'priceable_type'    => ProductPrice::class,
                'priceable_id'      => $newPrice->id,
                'product_id'        => $productId,
                'work_location_id'  => $workLocationId,
                'channel'           => $channel,
                'old_price'         => $old ? (string) $old->recommended_price : null,
                'new_price'         => $recommendedPrice,
                'changed_by'        => $actor->id,
                'source'            => 'manual',
                'reason'            => $reason,
                'effective_at'      => $today,
            ]);

            return $newPrice;
        });
    }

    /**
     * Salin harga aktif dari lokasi asal ke lokasi tujuan.
     *
     * Jika lokasi tujuan sudah punya harga aktif → tidak overwrite.
     *
     * @return ProductPrice|null harga yang di-copy, atau null jika sudah ada harga tujuan
     */
    public function copyPriceIfMissing(
        int $productId,
        int $fromWorkLocationId,
        int $toWorkLocationId,
        string $channel,
        User $actor,
    ): ?ProductPrice {
        $existing = $this->activePrice($productId, $toWorkLocationId, $channel);

        // Jika harga spesifik lokasi tujuan sudah ada → tidak overwrite
        if ($existing !== null && $existing->work_location_id === $toWorkLocationId) {
            return null;
        }

        $source = $this->activePrice($productId, $fromWorkLocationId, $channel);

        if ($source === null) {
            return null;
        }

        return $this->setLocationPrice(
            productId:        $productId,
            workLocationId:   $toWorkLocationId,
            channel:          $channel,
            recommendedPrice: (string) $source->recommended_price,
            actor:            $actor,
            minPrice:         $source->min_price !== null ? (string) $source->min_price : null,
            maxPrice:         $source->max_price !== null ? (string) $source->max_price : null,
            reason:           'Disalin dari lokasi asal saat penerimaan transfer.',
        );
    }
}
