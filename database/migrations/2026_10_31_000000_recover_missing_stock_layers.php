<?php

use App\Support\Decimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->where('stocks.quantity_on_hand', '>', 0)
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('stock_batches')
                ->whereColumn('stock_batches.stock_id', 'stocks.id'))
            ->select([
                'stocks.id as stock_id',
                'stocks.product_id',
                'stocks.work_location_id',
                'stocks.warehouse_location_id',
                'stocks.quantity_on_hand',
                'stocks.quantity_reserved',
                'stocks.quantity_damaged',
                'products.base_unit_id',
                'products.cost_price',
            ])
            ->orderBy('stocks.id')
            ->chunkById(200, function ($stocks): void {
                foreach ($stocks as $stock) {
                    $this->recoverStock($stock);
                }
            }, 'stocks.id', 'stock_id');
    }

    private function recoverStock(object $stock): void
    {
        $layers = [];
        $incomingTotal = '0.0000';
        $mutations = DB::table('stock_mutations')
            ->where('stock_id', $stock->stock_id)
            ->where('quantity_on_hand_change', '>', 0)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        foreach ($mutations as $mutation) {
            $quantity = Decimal::normalize((string) $mutation->quantity_on_hand_change, 4);
            $metadata = is_string($mutation->metadata) ? json_decode($mutation->metadata, true) : [];
            $unitCost = is_array($metadata) && isset($metadata['unit_cost'])
                ? Decimal::normalize((string) $metadata['unit_cost'], 2)
                : Decimal::normalize((string) $stock->cost_price, 2);

            $layers[] = [
                'source_type' => 'recovery_'.substr((string) ($mutation->reference_type ?: 'stock_mutation'), 0, 70),
                'source_id' => $mutation->id,
                'source_item_id' => is_array($metadata) ? ($metadata['emergency_purchase_item_id'] ?? null) : null,
                'batch_no' => 'RECOVER-'.$stock->stock_id.'-'.$mutation->id,
                'received_at' => substr((string) $mutation->occurred_at, 0, 10),
                'cost_price' => $unitCost,
                'quantity_on_hand' => $quantity,
            ];
            $incomingTotal = Decimal::add($incomingTotal, $quantity, 4);
        }

        if (Decimal::compare($incomingTotal, (string) $stock->quantity_on_hand, 4) < 0) {
            $gap = Decimal::sub((string) $stock->quantity_on_hand, $incomingTotal, 4);
            array_unshift($layers, [
                'source_type' => 'legacy_balance_recovery',
                'source_id' => $stock->stock_id,
                'source_item_id' => null,
                'batch_no' => 'RECOVER-'.$stock->stock_id.'-BALANCE',
                'received_at' => now()->toDateString(),
                'cost_price' => Decimal::normalize((string) $stock->cost_price, 2),
                'quantity_on_hand' => $gap,
            ]);
            $incomingTotal = Decimal::add($incomingTotal, $gap, 4);
        }

        $issued = Decimal::sub($incomingTotal, (string) $stock->quantity_on_hand, 4);
        foreach ($layers as &$layer) {
            if (Decimal::compare($issued, '0', 4) <= 0) {
                break;
            }

            $take = Decimal::compare($issued, $layer['quantity_on_hand'], 4) <= 0
                ? $issued
                : $layer['quantity_on_hand'];
            $layer['quantity_on_hand'] = Decimal::sub($layer['quantity_on_hand'], $take, 4);
            $issued = Decimal::sub($issued, $take, 4);
        }
        unset($layer);

        $reserved = Decimal::normalize((string) $stock->quantity_reserved, 4);
        $damaged = Decimal::normalize((string) $stock->quantity_damaged, 4);
        $now = now();
        $inserts = [];

        foreach ($layers as $layer) {
            if (Decimal::compare($layer['quantity_on_hand'], '0', 4) <= 0) {
                continue;
            }

            $layerReserved = Decimal::compare($reserved, $layer['quantity_on_hand'], 4) <= 0
                ? $reserved
                : $layer['quantity_on_hand'];
            $reserved = Decimal::sub($reserved, $layerReserved, 4);
            $remainingCapacity = Decimal::sub($layer['quantity_on_hand'], $layerReserved, 4);
            $layerDamaged = Decimal::compare($damaged, $remainingCapacity, 4) <= 0
                ? $damaged
                : $remainingCapacity;
            $damaged = Decimal::sub($damaged, $layerDamaged, 4);

            $inserts[] = [
                'source_batch_id' => null,
                'product_id' => $stock->product_id,
                'supplier_id' => null,
                'stock_id' => $stock->stock_id,
                'work_location_id' => $stock->work_location_id,
                'warehouse_location_id' => $stock->warehouse_location_id,
                'goods_receipt_id' => null,
                'goods_receipt_item_id' => null,
                'source_type' => $layer['source_type'],
                'source_id' => $layer['source_id'],
                'source_item_id' => $layer['source_item_id'],
                'unit_id' => $stock->base_unit_id,
                'conversion_factor_snapshot' => '1.000000',
                'batch_no' => $layer['batch_no'],
                'received_at' => $layer['received_at'],
                'expires_at' => null,
                'cost_price' => $layer['cost_price'],
                'entry_sale_price_snapshot' => null,
                'quantity_on_hand' => $layer['quantity_on_hand'],
                'quantity_reserved' => $layerReserved,
                'quantity_damaged' => $layerDamaged,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($inserts !== []) {
            DB::table('stock_batches')->insert($inserts);
        }
    }

    public function down(): void
    {
        // Lapisan yang sudah mungkin dipakai transaksi tidak aman untuk dihapus.
    }
};
