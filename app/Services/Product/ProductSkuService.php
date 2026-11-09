<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductSkuService
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $monthPrefix = now()->format('mY'); // Contoh: 112026 untuk November 2026
            $prefix = "PRD{$monthPrefix}";
            $prefixLen = strlen($prefix);

            // Cari SKU terakhir yang valid dengan prefix bulan ini untuk melanjutkan urutan
            $recentSkus = Product::query()
                ->withTrashed()
                ->where('sku', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->limit(50)
                ->pluck('sku');

            $maxSequence = 0;
            foreach ($recentSkus as $candidateSku) {
                if (is_string($candidateSku) && str_starts_with($candidateSku, $prefix)) {
                    $suffix = substr($candidateSku, $prefixLen);
                    // Hanya ambil suffix numerik wajar (maksimal 6 digit) agar terhindar dari integer overflow
                    if (ctype_digit($suffix) && strlen($suffix) <= 6) {
                        $seq = (int) $suffix;
                        if ($seq > $maxSequence) {
                            $maxSequence = $seq;
                        }
                    }
                }
            }

            $nextId = $maxSequence + 1;

            do {
                $sku = $prefix.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
                $nextId++;
            } while (Product::query()->withTrashed()->where('sku', $sku)->exists());

            return $sku;
        });
    }

    /**
     * Generate SKU unik berikutnya secara in-memory untuk proses bulk import.
     *
     * @param  array<string, int|bool>  $existingSkuMap
     */
    public function nextAvailableSku(string $prefix, int &$nextSequence, array &$existingSkuMap): string
    {
        do {
            $sku = $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            $nextSequence++;
        } while (isset($existingSkuMap[$sku]));

        return $sku;
    }
}
