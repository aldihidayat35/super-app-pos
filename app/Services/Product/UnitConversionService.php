<?php

namespace App\Services\Product;

use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\ProductUnit;

class UnitConversionService
{
    public function toBase(float|string $quantity, ProductUnit $productUnit): string
    {
        return bcmul((string) $quantity, (string) $productUnit->conversion_factor, 4);
    }

    public function fromBase(float|string $baseQuantity, ProductUnit $productUnit): string
    {
        if (bccomp((string) $productUnit->conversion_factor, '0', 6) <= 0) {
            throw new ServiceException('Faktor konversi harus lebih dari 0.');
        }

        return bcdiv((string) $baseQuantity, (string) $productUnit->conversion_factor, 4);
    }

    /** @param array<int, array<string, mixed>> $units */
    public function assertValidUnitPayload(array $units, int $baseUnitId): void
    {
        $baseRows = collect($units)->filter(fn (array $unit): bool => (int) ($unit['unit_id'] ?? 0) === $baseUnitId);

        if ($baseRows->count() !== 1) {
            throw new ServiceException('Produk wajib memiliki tepat satu satuan dasar.');
        }

        if (bccomp((string) $baseRows->first()['conversion_factor'], '1', 6) !== 0) {
            throw new ServiceException('Satuan dasar wajib memiliki faktor konversi 1.');
        }

        collect($units)->each(function (array $unit): void {
            if (bccomp((string) ($unit['conversion_factor'] ?? 0), '0', 6) <= 0) {
                throw new ServiceException('Faktor konversi setiap satuan harus lebih dari 0.');
            }
        });
    }

    /** @param array<int, array<string, mixed>> $units */
    public function syncProductUnits(Product $product, array $units): void
    {
        $existing = $product->units()->get()->keyBy('unit_id');
        $incomingIds = collect($units)->pluck('unit_id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach ($units as $unit) {
            $unitId = (int) $unit['unit_id'];
            /** @var ProductUnit|null $productUnit */
            $productUnit = $existing->get($unitId);
            $conversionFactor = (string) $unit['conversion_factor'];

            if ($productUnit && $productUnit->is_locked && bccomp((string) $productUnit->conversion_factor, $conversionFactor, 6) !== 0) {
                throw new ServiceException('Faktor konversi yang sudah dipakai transaksi tidak boleh diubah tanpa revisi.');
            }

            ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $unitId],
                [
                    'name' => $unit['name'] ?? null,
                    'conversion_factor' => $conversionFactor,
                    'is_base' => $unitId === (int) $product->base_unit_id,
                    'is_sellable' => (bool) ($unit['is_sellable'] ?? true),
                    'is_active' => (bool) ($unit['is_active'] ?? true),
                ],
            );
        }

        ProductUnit::query()
            ->where('product_id', $product->id)
            ->whereNotIn('unit_id', $incomingIds)
            ->where('is_locked', false)
            ->delete();
    }

    /** @return array<int, array<string, mixed>> */
    public function defaultUnitsPayload(int $baseUnitId): array
    {
        return [
            ['unit_id' => $baseUnitId, 'conversion_factor' => '1', 'is_sellable' => true, 'is_active' => true, 'name' => 'Satuan dasar'],
        ];
    }
}
