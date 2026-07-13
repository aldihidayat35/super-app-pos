<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductSkuService
{
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $nextId = ((int) Product::query()->withTrashed()->lockForUpdate()->max('id')) + 1;

            do {
                $sku = 'PRD-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
                $nextId++;
            } while (Product::query()->withTrashed()->where('sku', $sku)->exists());

            return $sku;
        });
    }
}
