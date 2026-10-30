<?php

namespace App\Services\Inventory;

use App\Exceptions\ServiceException;
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
                'source_batch_id'            => $source->id,
                'product_id'                 => $source->product_id,
                'supplier_id'                => $source->supplier_id,
                'unit_id'                    => $source->unit_id,
                'conversion_factor_snapshot' => (string) $source->conversion_factor_snapshot,
                'batch_no'                   => $source->batch_no,
                'expires_at'                 => $source->expires_at instanceof \DateTimeInterface
                    ? $source->expires_at->format('Y-m-d')
                    : (filled($source->expires_at) ? (string) $source->expires_at : null),
                'cost_price'                 => (string) $source->cost_price,
                'status'                     => 'active',
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
                'stock_batch_id'       => $batch->id,
                'product_id'           => $batch->product_id,
                'work_location_id'     => $batch->work_location_id,
                'warehouse_location_id'=> $batch->warehouse_location_id,
                'reference_type'       => $data['reference_type'],
                'reference_id'         => $data['reference_id'],
                'event_type'           => $data['event_type'],
                'quantity'             => $data['quantity'],
                'unit_cost'            => (string) $batch->cost_price,
                'sale_price_snapshot'  => $data['sale_price_snapshot'] ?? null,
                'actor_user_id'        => $data['actor_user_id'] ?? null,
                'metadata'             => $data['metadata'] ?? null,
                'occurred_at'          => $data['occurred_at'] ?? now(),
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

            $locked->quantity_on_hand = Decimal::sub((string) $locked->quantity_on_hand, $quantity);
            $locked->save();

            $batch->quantity_on_hand = $locked->quantity_on_hand;
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

            $locked->quantity_on_hand = Decimal::add((string) $locked->quantity_on_hand, $quantity);
            $locked->save();

            $batch->quantity_on_hand = $locked->quantity_on_hand;
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
     * @param list<int>|null $selectedBatchIds  null = auto-select
     * @param string         $quantity          Kuantitas yang dibutuhkan (base unit)
     * @return list<array{batch: StockBatch, quantity: string}>
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
            throw ServiceException::validation('Pilih batch yang akan digunakan untuk transaksi ini.');
        }

        // Filter ke lapisan yang dipilih (pertahankan urutan FIFO)
        $selected = $available->filter(
            fn (StockBatch $b) => in_array($b->id, $selectedBatchIds, true)
        )->values();

        // Hitung total ketersediaan dari batch yang dipilih
        $totalAvailable = '0.0000';
        foreach ($selected as $batch) {
            $totalAvailable = Decimal::add($totalAvailable, $batch->availableQuantity());
        }

        if (Decimal::compare($quantity, $totalAvailable) > 0) {
            throw ServiceException::validation('Stok tidak mencukupi dari batch yang dipilih.');
        }

        // Distribusi quantity secara berurutan (FIFO dari pilihan)
        $result   = [];
        $remaining = $quantity;

        foreach ($selected as $batch) {
            if (Decimal::isZero($remaining)) {
                break;
            }

            $take = Decimal::compare($remaining, $batch->availableQuantity()) <= 0
                ? $remaining
                : $batch->availableQuantity();

            $result[]  = ['batch' => $batch, 'quantity' => $take];
            $remaining = Decimal::sub($remaining, $take);
        }

        return $result;
    }
}
