<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductSkuService
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $monthPrefix = now()->format('mY'); // MMYY, contoh: 0826 untuk Agustus 2026

            // Cari SKU terakhir dengan prefix bulan ini untuk reset urutan
            $lastSku = Product::query()
                ->withTrashed()
                ->where('sku', 'like', "PRD{$monthPrefix}%")
                ->orderByDesc('id')
                ->value('sku');

            if ($lastSku && str_starts_with($lastSku, "PRD{$monthPrefix}")) {
                $sequence = (int) substr($lastSku, 5); // Ambil 4 digit terakhir setelah PRDMMYY
                $nextId = $sequence + 1;
            } else {
                $nextId = 1;
            }

            do {
                $sku = 'PRD'.str_pad((string) $monthPrefix, 6, '', STR_PAD_LEFT).str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
                $nextId++;
            } while (Product::query()->withTrashed()->where('sku', $sku)->exists());

            return $sku;
        });
    }
}
