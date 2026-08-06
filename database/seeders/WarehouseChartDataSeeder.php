<?php

namespace Database\Seeders;

use App\Enums\StockMutationType;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseChartDataSeeder extends Seeder
{
    /**
     * Seeder ini mengisi data historis stock_mutations agar dashboard
     * Gudang Jambu Air menampilkan grafik Pergerakan Stok dan Distribusi Stok.
     *
     * Gunakan:
     *   php artisan db:seed WarehouseChartDataSeeder
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('stock_mutations')->truncate();
        DB::table('stocks')->truncate();
        DB::table('stock_batches')->truncate();

        Schema::enableForeignKeyConstraints();

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()
            ->where('code', 'GDG-JB')
            ->firstOrFail();

        /** @var WorkLocation $workLocation */
        $workLocation = WorkLocation::query()
            ->findOrFail($warehouse->work_location_id);

        /** @var WarehouseLocation $bin */
        $bin = WarehouseLocation::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('type', 'bin')
            ->first();

        if (! $bin) {
            $bin = WarehouseLocation::query()
                ->where('work_location_id', $workLocation->id)
                ->where('type', 'bin')
                ->first();
        }

        if (! $bin) {
            $this->command->warn('Gudang Jambu Air belum punya bin. Buat bin dummy.');
            $rack = WarehouseLocation::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('type', 'rack')
                ->first();

            $bin = WarehouseLocation::query()->create([
                'warehouse_id' => $warehouse->id,
                'parent_id' => $rack?->id,
                'type' => 'bin',
                'code' => 'BIN-CHART',
                'name' => 'Bin Chart Data',
                'capacity' => 500,
                'item_type' => 'Campuran',
                'is_active' => true,
                'full_code' => 'GDG-JB-CHART-BIN',
            ]);
        }

        $products = Product::query()
            ->where('status', 'active')
            ->limit(5)
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn('Tidak ada produk aktif. Seeder dilewati.');

            return;
        }

        $admin = User::query()
            ->where('email', 'superadmin@gudangtoko.test')
            ->first();

        if (! $admin) {
            $this->command->warn('User superadmin tidak ditemukan. Seeder dilewati.');

            return;
        }

        $today = now('Asia/Jakarta');

        // Simpan opening stock agar donut chart menampilkan distribusi (tersedia vs lainnya).
        $this->seedOpeningStock($products, $workLocation, $bin, $admin);

        // Pola variasi mutasi 7 hari terakhir untuk grafik area.
        $mutations = $this->generateVariations($products, $workLocation, $bin, $admin, $today);

        foreach ($mutations as $mutation) {
            StockMutation::create($mutation);
        }
    }

    /**
     * Buat opening stock agar donut chart Distribusi Stok memuat data.
     *
     * Distribusi donut akan memuat:
     * - tersedia (available)
     * - dipesan (reserved)
     * - rusak (damaged)
     */
    private function seedOpeningStock($products, $workLocation, $bin, $admin): void
    {
        $pattern = [
            ['qty' => 450, 'reserved' => 0, 'damaged' => 0, 'label' => 'stok utama'],
            ['qty' => 300, 'reserved' => 50, 'damaged' => 0, 'label' => 'stok penjualan'],
            ['qty' => 200, 'reserved' => 0, 'damaged' => 0, 'label' => 'stok cadangan'],
            ['qty' => 100, 'reserved' => 0, 'damaged' => 15, 'label' => 'stok kritis'],
            ['qty' => 60, 'reserved' => 20, 'damaged' => 5, 'label' => 'stok terbatas'],
        ];

        foreach ($products as $index => $product) {
            $stockData = $pattern[$index] ?? ['qty' => 100, 'reserved' => 0, 'damaged' => 0, 'label' => 'stok'];

            $stock = Stock::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => $bin->id,
                ],
                [
                    'location_scope_key' => 'wh:'.$workLocation->id.':'.$bin->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'quantity_damaged' => 0,
                    'cost_value' => 0,
                ],
            );

            // Mutasi receive sebagai opening.
            StockMutation::create([
                'product_id' => $product->id,
                'stock_id' => $stock->id,
                'work_location_id' => $workLocation->id,
                'warehouse_location_id' => $bin->id,
                'mutation_type' => StockMutationType::RECEIVE->value,
                'direction' => 'in',
                'quantity_on_hand_before' => 0,
                'quantity_on_hand_change' => $stockData['qty'],
                'quantity_on_hand_after' => $stockData['qty'],
                'quantity_reserved_before' => 0,
                'quantity_reserved_change' => 0,
                'quantity_reserved_after' => 0,
                'quantity_damaged_before' => 0,
                'quantity_damaged_change' => 0,
                'quantity_damaged_after' => 0,
                'reference_type' => 'opening_stock',
                'reference_id' => null,
                'reference_no' => 'OPN-'.strtoupper($product->code),
                'actor_user_id' => $admin->id,
                'occurred_at' => now('Asia/Jakarta')->startOfDay(),
            ]);

            // Reservasi dummy untuk donut.
            if ($stockData['reserved'] > 0) {
                StockMutation::create([
                    'product_id' => $product->id,
                    'stock_id' => $stock->id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => $bin->id,
                    'mutation_type' => StockMutationType::RESERVE->value,
                    'direction' => 'in',
                    'quantity_on_hand_before' => $stockData['qty'],
                    'quantity_on_hand_change' => 0,
                    'quantity_on_hand_after' => $stockData['qty'],
                    'quantity_reserved_before' => 0,
                    'quantity_reserved_change' => $stockData['reserved'],
                    'quantity_reserved_after' => $stockData['reserved'],
                    'quantity_damaged_before' => 0,
                    'quantity_damaged_change' => 0,
                    'quantity_damaged_after' => 0,
                    'reference_type' => 'opening_reservation',
                    'reference_id' => null,
                    'reference_no' => 'RSV-OPN-'.strtoupper($product->code),
                    'actor_user_id' => $admin->id,
                    'occurred_at' => now('Asia/Jakarta')->startOfDay(),
                ]);
            }

            // Kerusakan dummy untuk donut.
            if ($stockData['damaged'] > 0) {
                StockMutation::create([
                    'product_id' => $product->id,
                    'stock_id' => $stock->id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => $bin->id,
                    'mutation_type' => StockMutationType::DAMAGE->value,
                    'direction' => 'out',
                    'quantity_on_hand_before' => $stockData['qty'],
                    'quantity_on_hand_change' => 0,
                    'quantity_on_hand_after' => $stockData['qty'],
                    'quantity_reserved_before' => 0,
                    'quantity_reserved_change' => 0,
                    'quantity_reserved_after' => 0,
                    'quantity_damaged_before' => 0,
                    'quantity_damaged_change' => $stockData['damaged'],
                    'quantity_damaged_after' => $stockData['damaged'],
                    'reference_type' => 'opening_damage',
                    'reference_id' => null,
                    'reference_no' => 'DMG-OPN-'.strtoupper($product->code),
                    'actor_user_id' => $admin->id,
                    'occurred_at' => now('Asia/Jakarta')->startOfDay(),
                ]);
            }

            // Update stocks table agar donut chart Distribusi Stok memuat data.
            Stock::where('product_id', $product->id)
                ->where('work_location_id', $workLocation->id)
                ->update([
                    'quantity_on_hand' => $stockData['qty'],
                    'quantity_reserved' => $stockData['reserved'],
                    'quantity_damaged' => $stockData['damaged'],
                ]);
        }
    }

    /**
     * Buat variasi mutasi harian agar grafik area pergerakkan stok tampil variatif.
     *
     * Pola:
     * - 7 hari terakhir
     * - Mix receive, issue, transfer, damage, return
     * - Jumlah bervariasi tiap hari agar grafik area tidak datar.
     */
    private function generateVariations($products, $workLocation, $bin, $admin, $today): array
    {
        $mutations = [];

        // 7 hari terakhir.
        for ($day = 6; $day >= 0; $day--) {
            $date = clone $today;
            $date->subDays($day);
            $date->setTime(9 + $day % 3, 0, 0);

            foreach ($products as $product) {
                // Pola receive: naik turun tiap hari.
                $receiveQty = (10 + ($day * 5) + ($product->id * 3)) % 50;
                $receiveQty = max(10, $receiveQty);

                $mutations[] = [
                    'product_id' => $product->id,
                    'stock_id' => $product->stocks()
                        ->where('work_location_id', $workLocation->id)
                        ->where('warehouse_location_id', $bin->id)
                        ->first()?->id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => $bin->id,
                    'mutation_type' => StockMutationType::RECEIVE->value,
                    'direction' => 'in',
                    'quantity_on_hand_before' => 0,
                    'quantity_on_hand_change' => $receiveQty,
                    'quantity_on_hand_after' => $receiveQty,
                    'quantity_reserved_before' => 0,
                    'quantity_reserved_change' => 0,
                    'quantity_reserved_after' => 0,
                    'quantity_damaged_before' => 0,
                    'quantity_damaged_change' => 0,
                    'quantity_damaged_after' => 0,
                    'reference_type' => 'receive',
                    'reference_id' => null,
                    'reference_no' => 'RCV-'.strtoupper($product->code).'-'.now('Asia/Jakarta')->format('dmY'),
                    'source_work_location_id' => $workLocation->id,
                    'source_warehouse_location_id' => $bin->id,
                    'actor_user_id' => $admin->id,
                    'occurred_at' => $date->copy(),
                ];

                // Pola issue: lebih variatif.
                $issueQty = (5 + ($day * 3) + ($product->id * 2)) % 30;
                $issueQty = max(5, $issueQty);

                $mutations[] = [
                    'product_id' => $product->id,
                    'stock_id' => $product->stocks()
                        ->where('work_location_id', $workLocation->id)
                        ->where('warehouse_location_id', $bin->id)
                        ->first()?->id,
                    'work_location_id' => $workLocation->id,
                    'warehouse_location_id' => $bin->id,
                    'mutation_type' => StockMutationType::ISSUE->value,
                    'direction' => 'out',
                    'quantity_on_hand_before' => $issueQty + 10,
                    'quantity_on_hand_change' => -$issueQty,
                    'quantity_on_hand_after' => 10,
                    'quantity_reserved_before' => 0,
                    'quantity_reserved_change' => 0,
                    'quantity_reserved_after' => 0,
                    'quantity_damaged_before' => 0,
                    'quantity_damaged_change' => 0,
                    'quantity_damaged_after' => 0,
                    'reference_type' => 'issue',
                    'reference_id' => null,
                    'reference_no' => 'ISS-'.strtoupper($product->code).'-'.now('Asia/Jakarta')->format('dmY'),
                    'destination_work_location_id' => $workLocation->id,
                    'destination_warehouse_location_id' => $bin->id,
                    'actor_user_id' => $admin->id,
                    'occurred_at' => $date->copy()->addHours(2),
                ];

                // Kadang tambah transfer in/out.
                if ($day % 2 === 0) {
                    $transferQty = 15;
                    $mutations[] = [
                        'product_id' => $product->id,
                        'stock_id' => $product->stocks()
                            ->where('work_location_id', $workLocation->id)
                            ->where('warehouse_location_id', $bin->id)
                            ->first()?->id,
                        'work_location_id' => $workLocation->id,
                        'warehouse_location_id' => $bin->id,
                        'mutation_type' => StockMutationType::TRANSFER_IN->value,
                        'direction' => 'in',
                        'quantity_on_hand_before' => 0,
                        'quantity_on_hand_change' => $transferQty,
                        'quantity_on_hand_after' => $transferQty,
                        'quantity_reserved_before' => 0,
                        'quantity_reserved_change' => 0,
                        'quantity_reserved_after' => 0,
                        'quantity_damaged_before' => 0,
                        'quantity_damaged_change' => 0,
                        'quantity_damaged_after' => 0,
                        'reference_type' => 'stock_transfer',
                        'reference_id' => null,
                        'reference_no' => 'TRF-IN-'.strtoupper($product->code).'-'.now('Asia/Jakarta')->format('dmY'),
                        'source_work_location_id' => $workLocation->id,
                        'source_warehouse_location_id' => $bin->id,
                        'actor_user_id' => $admin->id,
                        'occurred_at' => $date->copy()->addHours(4),
                    ];

                    $mutations[] = [
                        'product_id' => $product->id,
                        'stock_id' => $product->stocks()
                            ->where('work_location_id', $workLocation->id)
                            ->where('warehouse_location_id', $bin->id)
                            ->first()?->id,
                        'work_location_id' => $workLocation->id,
                        'warehouse_location_id' => $bin->id,
                        'mutation_type' => StockMutationType::TRANSFER_OUT->value,
                        'direction' => 'out',
                        'quantity_on_hand_before' => $transferQty + 10,
                        'quantity_on_hand_change' => -$transferQty,
                        'quantity_on_hand_after' => 10,
                        'quantity_reserved_before' => 0,
                        'quantity_reserved_change' => 0,
                        'quantity_reserved_after' => 0,
                        'quantity_damaged_before' => 0,
                        'quantity_damaged_change' => 0,
                        'quantity_damaged_after' => 0,
                        'reference_type' => 'stock_transfer',
                        'reference_id' => null,
                        'reference_no' => 'TRF-OUT-'.strtoupper($product->code).'-'.now('Asia/Jakarta')->format('dmY'),
                        'destination_work_location_id' => $workLocation->id,
                        'destination_warehouse_location_id' => $bin->id,
                        'actor_user_id' => $admin->id,
                        'occurred_at' => $date->copy()->addHours(5),
                    ];
                }

                // Kadang tambah return.
                if ($day % 3 === 0) {
                    $returnQty = 8;
                    $mutations[] = [
                        'product_id' => $product->id,
                        'stock_id' => $product->stocks()
                            ->where('work_location_id', $workLocation->id)
                            ->where('warehouse_location_id', $bin->id)
                            ->first()?->id,
                        'work_location_id' => $workLocation->id,
                        'warehouse_location_id' => $bin->id,
                        'mutation_type' => StockMutationType::RETURN_IN->value,
                        'direction' => 'in',
                        'quantity_on_hand_before' => 0,
                        'quantity_on_hand_change' => $returnQty,
                        'quantity_on_hand_after' => $returnQty,
                        'quantity_reserved_before' => 0,
                        'quantity_reserved_change' => 0,
                        'quantity_reserved_after' => 0,
                        'quantity_damaged_before' => 0,
                        'quantity_damaged_change' => 0,
                        'quantity_damaged_after' => 0,
                        'reference_type' => 'return',
                        'reference_id' => null,
                        'reference_no' => 'RTN-'.strtoupper($product->code).'-'.now('Asia/Jakarta')->format('dmY'),
                        'actor_user_id' => $admin->id,
                        'occurred_at' => $date->copy()->addHours(6),
                    ];
                }
            }
        }

        return $mutations;
    }
}
