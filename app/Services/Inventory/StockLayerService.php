<?php

namespace App\Services\Inventory;

use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockLayerAllocation;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockLayerService
{
    /**
     * Membuat lapisan stok baru (dari GR, stok awal, koreksi +, dll).
     *
     * @param array{
     *   product_id: int,
     *   stock_id: int|null,
     *   work_location_id: int,
     *   warehouse_location_id: int|null,
     *   source_type: string,
     *   source_id: int|null,
     *   source_item_id: int|null,
     *   source_batch_id: int|null,
     *   supplier_id: int|null,
     *   goods_receipt_id: int|null,
     *   goods_receipt_item_id: int|null,
     *   unit_id: int|null,
     *   conversion_factor_snapshot: string,
     *   batch_no: string,
     *   received_at: string,
     *   expires_at: string|null,
     *   cost_price: string,
     *   entry_sale_price_snapshot: string|null,
     *   quantity_on_hand: string,
     *   quantity_reserved: string,
     *   quantity_damaged: string,
     * } $data
     *
     * @throws ServiceException
     */
    public function createLayer(array $data): StockBatch
    {
        if (! Decimal::isPositive($data['cost_price'])) {
            throw ServiceException::validation('HPP harus lebih dari 0.');
        }

        return DB::transaction(function () use ($data): StockBatch {
            /** @var StockBatch */
            return StockBatch::create(array_merge($data, ['status' => 'active']));
        });
    }

    /**
     * Membuat lapisan turunan dari lapisan asal untuk transfer.
     * HPP diwarisi dari $source.
     *
     * @param array{
     *   stock_id: int|null,
     *   work_location_id: int,
     *   warehouse_location_id: int|null,
     *   source_type: string,
     *   source_id: int,
     *   source_item_id: int|null,
     *   entry_sale_price_snapshot: string|null,
     *   quantity_on_hand: string,
     *   quantity_reserved: string,
     *   quantity_damaged: string,
     *   received_at: string,
     * } $overrides
     */
    public function deriveLayer(StockBatch $source, array $overrides): StockBatch
    {
        return DB::transaction(function () use ($source, $overrides): StockBatch {
            /** @var StockBatch */
            return StockBatch::create(array_merge([
                'source_batch_id' => $source->id,
                'product_id' => $source->product_id,
                'supplier_id' => $source->supplier_id,
                'unit_id' => $source->unit_id,
                'conversion_factor_snapshot' => (string) $source->conversion_factor_snapshot,
                'batch_no' => $source->batch_no,
                'expires_at' => filled($source->getRawOriginal('expires_at'))
                    ? (string) $source->getRawOriginal('expires_at')
                    : null,
                'cost_price' => (string) $source->cost_price,
                'status' => 'active',
            ], $overrides));
        });
    }

    /**
     * Mencatat alokasi lapisan untuk satu event.
     *
     * @param array{
     *   reference_type: string,
     *   reference_id: int,
     *   event_type: string,
     *   quantity: string,
     *   sale_price_snapshot: string|null,
     *   actor_user_id: int|null,
     *   metadata: array<string,mixed>|null,
     *   occurred_at: Carbon|null,
     * } $data
     *
     * @throws ServiceException
     */
    public function recordAllocation(StockBatch $batch, array $data): StockLayerAllocation
    {
        if (! Decimal::isPositive($data['quantity'])) {
            throw ServiceException::validation('Kuantitas alokasi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($batch, $data): StockLayerAllocation {
            /** @var StockLayerAllocation */
            return StockLayerAllocation::create([
                'stock_batch_id' => $batch->id,
                'product_id' => $batch->product_id,
                'work_location_id' => $batch->work_location_id,
                'warehouse_location_id' => $batch->warehouse_location_id,
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'event_type' => $data['event_type'],
                'quantity' => $data['quantity'],
                'unit_cost' => (string) $batch->cost_price,
                'sale_price_snapshot' => $data['sale_price_snapshot'] ?? null,
                'actor_user_id' => $data['actor_user_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });
    }

    /**
     * Mengurangi quantity_on_hand lapisan. Menggunakan lockForUpdate() untuk menghindari race condition.
     *
     * @throws ServiceException jika stok tidak cukup
     */
    public function deductLayer(StockBatch $batch, string $quantity): void
    {
        if (! Decimal::isPositive($quantity)) {
            throw ServiceException::validation('Kuantitas pengurangan harus lebih dari 0.');
        }

        DB::transaction(function () use ($batch, $quantity): void {
            /** @var StockBatch $locked */
            $locked = StockBatch::lockForUpdate()->findOrFail($batch->id);

            if (Decimal::compare($quantity, $locked->availableQuantity()) > 0) {
                throw ServiceException::validation('Stok batch tidak mencukupi.');
            }

            $locked->forceFill([
                'quantity_on_hand' => Decimal::sub((string) $locked->quantity_on_hand, $quantity),
            ])->save();
        });
    }

    /** Reserve kuantitas pada lapisan yang dipilih tanpa mengubah saldo on hand. */
    public function reserveLayer(StockBatch $batch, string $quantity): void
    {
        if (! Decimal::isPositive($quantity)) {
            throw ServiceException::validation('Kuantitas reserve harus lebih dari 0.');
        }

        DB::transaction(function () use ($batch, $quantity): void {
            $locked = StockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (Decimal::compare($quantity, $locked->availableQuantity()) > 0) {
                throw ServiceException::validation('Stok batch tidak mencukupi untuk di-reserve.');
            }
            $locked->forceFill([
                'quantity_reserved' => Decimal::add((string) $locked->quantity_reserved, $quantity),
            ])->save();
            $batch->quantity_reserved = $locked->quantity_reserved;
        });
    }

    /** Lepas reserve lapisan. Dipakai sebelum pengeluaran atau pembatalan. */
    public function releaseLayerReservation(StockBatch $batch, string $quantity): void
    {
        if (! Decimal::isPositive($quantity)) {
            throw ServiceException::validation('Kuantitas pelepasan reserve harus lebih dari 0.');
        }

        DB::transaction(function () use ($batch, $quantity): void {
            $locked = StockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (Decimal::compare($quantity, (string) $locked->quantity_reserved) > 0) {
                throw ServiceException::validation('Kuantitas pelepasan melebihi reserve batch.');
            }
            $locked->forceFill([
                'quantity_reserved' => Decimal::sub((string) $locked->quantity_reserved, $quantity),
            ])->save();
            $batch->quantity_reserved = $locked->quantity_reserved;
        });
    }

    /**
     * Mengeluarkan lapisan yang sebelumnya sudah di-reserve.
     */
    public function issueReservedLayer(StockBatch $batch, string $quantity): void
    {
        if (! Decimal::isPositive($quantity)) {
            throw ServiceException::validation('Kuantitas pengeluaran harus lebih dari 0.');
        }

        DB::transaction(function () use ($batch, $quantity): void {
            $locked = StockBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (Decimal::compare($quantity, (string) $locked->quantity_reserved) > 0
                || Decimal::compare($quantity, (string) $locked->quantity_on_hand) > 0) {
                throw ServiceException::validation('Stok atau reserve batch tidak mencukupi.');
            }
            $locked->forceFill([
                'quantity_on_hand' => Decimal::sub((string) $locked->quantity_on_hand, $quantity),
                'quantity_reserved' => Decimal::sub((string) $locked->quantity_reserved, $quantity),
            ])->save();
            $batch->quantity_on_hand = $locked->quantity_on_hand;
            $batch->quantity_reserved = $locked->quantity_reserved;
        });
    }

    /**
     * Mengembalikan quantity ke lapisan (void/retur). Menggunakan lockForUpdate().
     *
     * @throws ServiceException
     */
    public function restoreLayer(StockBatch $batch, string $quantity): void
    {
        if (! Decimal::isPositive($quantity)) {
            throw ServiceException::validation('Kuantitas pengembalian harus lebih dari 0.');
        }

        DB::transaction(function () use ($batch, $quantity): void {
            /** @var StockBatch $locked */
            $locked = StockBatch::lockForUpdate()->findOrFail($batch->id);

            $locked->forceFill([
                'quantity_on_hand' => Decimal::add((string) $locked->quantity_on_hand, $quantity),
            ])->save();
        });
    }

    /**
     * Query lapisan tersedia (active, qty > 0) untuk produk di lokasi tertentu.
     * Diurutkan: received_at ASC (FIFO), kemudian id ASC.
     *
     * @return Collection<int, StockBatch>
     */
    public function availableLayers(int $productId, int $workLocationId, ?int $warehouseLocationId = null): Collection
    {
        return StockBatch::available()
            ->forProduct($productId)
            ->atLocation($workLocationId, $warehouseLocationId)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Hitung nilai persediaan: SUM(qty × HPP) untuk semua lapisan aktif.
     * Jika $productId null, hitung semua produk di lokasi.
     */
    public function inventoryValue(int $workLocationId, ?int $productId = null): string
    {
        $query = StockBatch::active()->atLocation($workLocationId);

        if ($productId !== null) {
            $query->forProduct($productId);
        }

        $total = '0.00';

        foreach ($query->get() as $batch) {
            $total = Decimal::add($total, $batch->totalValue(), scale: 2);
        }

        return $total;
    }

    /**
     * Otomatis pilih lapisan jika hanya ada 1, atau validasi pilihan manual.
     *
     * @param  list<int>|null  $selectedBatchIds  null = auto-select
     * @param  string  $quantity  Kuantitas yang dibutuhkan (base unit)
     * @return list<array{batch: StockBatch, quantity: string}>
     *
     * @throws ServiceException
     */
    public function resolveLayers(
        int $productId,
        int $workLocationId,
        string $quantity,
        ?int $warehouseLocationId = null,
        ?array $selectedBatchIds = null,
    ): array {
        $available = $this->availableLayers($productId, $workLocationId, $warehouseLocationId);

        if ($available->isEmpty()) {
            // Jembatan aman untuk saldo lama/integrasi teknis yang sudah menambah Stock
            // sebelum layer tersedia. Transaksi baru tetap wajib memakai layer sesudah ini.
            $stock = Stock::query()
                ->where('product_id', $productId)
                ->where('work_location_id', $workLocationId)
                ->when($warehouseLocationId !== null,
                    fn ($query) => $query->where('warehouse_location_id', $warehouseLocationId),
                    fn ($query) => $query->whereNull('warehouse_location_id'))
                ->lockForUpdate()
                ->first();
            $product = Product::query()->find($productId);
            if ($stock instanceof Stock && $product instanceof Product
                && Decimal::compare((string) $stock->quantity_on_hand, '0', 4) > 0) {
                // Saldo legacy boleh membawa HPP 0 persis seperti data lama; larangan
                // HPP 0 tetap berlaku untuk createLayer pada transaksi stok masuk baru.
                StockBatch::query()->create([
                    'source_batch_id' => null,
                    'product_id' => $productId,
                    'stock_id' => $stock->id,
                    'work_location_id' => $workLocationId,
                    'warehouse_location_id' => $warehouseLocationId,
                    'source_type' => 'legacy_runtime_bridge',
                    'source_id' => $stock->id,
                    'source_item_id' => null,
                    'supplier_id' => null,
                    'goods_receipt_id' => null,
                    'goods_receipt_item_id' => null,
                    'unit_id' => $product->base_unit_id,
                    'conversion_factor_snapshot' => '1.000000',
                    'batch_no' => 'LEGACY-'.$stock->id,
                    'received_at' => now()->toDateString(),
                    'expires_at' => null,
                    'cost_price' => (string) $product->cost_price,
                    'entry_sale_price_snapshot' => null,
                    'quantity_on_hand' => (string) $stock->quantity_on_hand,
                    'quantity_reserved' => (string) $stock->quantity_reserved,
                    'quantity_damaged' => (string) $stock->quantity_damaged,
                    'status' => 'active',
                ]);
                $available = $this->availableLayers($productId, $workLocationId, $warehouseLocationId);
            }
        }

        if ($available->isEmpty()) {
            throw ServiceException::validation('Tidak ada stok tersedia untuk produk ini di lokasi yang dipilih.');
        }

        // Jika hanya 1 lapisan → otomatis pilih
        if ($available->count() === 1) {
            $only = $available->first();

            if (Decimal::compare($quantity, $only->availableQuantity()) > 0) {
                throw ServiceException::validation('Stok tidak mencukupi dari batch yang dipilih.');
            }

            return [['batch' => $only, 'quantity' => $quantity]];
        }

        // Lebih dari 1 lapisan → wajib pilih
        if (empty($selectedBatchIds)) {
            $selectedBatchIds = $available->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        // Filter ke lapisan yang dipilih (pertahankan urutan FIFO)
        $selected = $available->filter(
            fn (StockBatch $b) => in_array($b->id, $selectedBatchIds, true)
        )->values();

        if ($selected->count() !== count(array_unique($selectedBatchIds))) {
            throw ServiceException::validation('Pilihan batch tidak valid atau tidak berada di lokasi transaksi.');
        }

        // Hitung total ketersediaan dari batch yang dipilih
        $totalAvailable = '0.0000';
        foreach ($selected as $batch) {
            $totalAvailable = Decimal::add($totalAvailable, $batch->availableQuantity());
        }

        if (Decimal::compare($quantity, $totalAvailable) > 0) {
            throw ServiceException::validation('Stok tidak mencukupi dari batch yang dipilih.');
        }

        // Distribusi quantity secara berurutan (FIFO dari pilihan)
        $result = [];
        $remaining = $quantity;

        foreach ($selected as $batch) {
            if (Decimal::isZero($remaining)) {
                break;
            }

            $take = Decimal::compare($remaining, $batch->availableQuantity()) <= 0
                ? $remaining
                : $batch->availableQuantity();

            $result[] = ['batch' => $batch, 'quantity' => $take];
            $remaining = Decimal::sub($remaining, $take);
        }

        return $result;
    }

    /** @param list<array{batch: StockBatch, quantity: string}> $allocations */
    public function weightedAverageCost(array $allocations): string
    {
        $quantity = '0.0000';
        $value = '0.00';
        foreach ($allocations as $allocation) {
            $quantity = Decimal::add($quantity, $allocation['quantity'], 4);
            $value = Decimal::add(
                $value,
                Decimal::mul($allocation['quantity'], (string) $allocation['batch']->cost_price, 4, 2, 2),
                2,
            );
        }

        return Decimal::compare($quantity, '0', 4) > 0
            ? Decimal::div($value, $quantity, 2, 4, 2)
            : '0.00';
    }
}
