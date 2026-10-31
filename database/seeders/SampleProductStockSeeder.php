<?php

namespace Database\Seeders;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleProductStockSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Seeder produk sampel hanya boleh dijalankan di environment local atau testing.');

        DB::transaction(function (): void {
            $user = User::query()->where('username', 'superadmin')->first()
                ?? User::query()->firstOrFail();

            $unitPcs = Unit::query()->firstOrCreate(
                ['name' => 'Pieces'],
                ['code' => 'PCS', 'symbol' => 'pcs', 'is_active' => true],
            );

            // 1. Setup Suppliers
            $supplierPlastik = Supplier::query()->updateOrCreate(
                ['code' => 'SUP-PLS-001'],
                [
                    'name' => 'PT Surya Plastik Mandiri',
                    'contact_name' => 'Budi Santoso',
                    'phone_number' => '081234567890',
                    'whatsapp_number' => '081234567890',
                    'email' => 'sales@suryaplastik.test',
                    'address' => 'Kawasan Industri Cikarang Blok B-12',
                    'city' => 'Bekasi',
                    'payment_term_days' => 30,
                    'is_active' => true,
                ],
            );

            $supplierKaca = Supplier::query()->updateOrCreate(
                ['code' => 'SUP-KCA-001'],
                [
                    'name' => 'CV Kaca Prima Glass',
                    'contact_name' => 'Hendra Wijaya',
                    'phone_number' => '081298765432',
                    'whatsapp_number' => '081298765432',
                    'email' => 'order@primaglass.test',
                    'address' => 'Jl. Industri Gelas No. 88',
                    'city' => 'Surabaya',
                    'payment_term_days' => 14,
                    'is_active' => true,
                ],
            );

            $supplierDapur = Supplier::query()->updateOrCreate(
                ['code' => 'SUP-DAP-001'],
                [
                    'name' => 'PT Dapur Makmur Sejahtera',
                    'contact_name' => 'Siti Rahma',
                    'phone_number' => '081377889900',
                    'whatsapp_number' => '081377889900',
                    'email' => 'info@dapurmakmur.test',
                    'address' => 'Jl. Raya Daan Mogot KM 14',
                    'city' => 'Jakarta Barat',
                    'payment_term_days' => 30,
                    'is_active' => true,
                ],
            );

            // 2. Setup Brands
            $brandLion = ProductBrand::query()->updateOrCreate(
                ['code' => 'BRD-LION'],
                ['name' => 'Lion Star', 'is_active' => true],
            );
            $brandMaspion = ProductBrand::query()->updateOrCreate(
                ['code' => 'BRD-MASP'],
                ['name' => 'Maspion', 'is_active' => true],
            );
            $brandKedaung = ProductBrand::query()->updateOrCreate(
                ['code' => 'BRD-KEDG'],
                ['name' => 'Kedaung', 'is_active' => true],
            );

            // 3. Setup Categories
            $catKaca = ProductCategory::query()->firstOrCreate(
                ['name' => 'Kaca'],
                ['code' => 'CAT-KCA', 'is_active' => true],
            );
            $catPlastik = ProductCategory::query()->firstOrCreate(
                ['name' => 'pelastik'],
                ['code' => 'CAT-PLS', 'is_active' => true],
            );

            // 4. Locations
            $tokoLocation = WorkLocation::query()->where('type', 'branch')->first()
                ?? WorkLocation::query()->find(2);
            $gudangLocation = WorkLocation::query()->where('type', 'warehouse')->first()
                ?? WorkLocation::query()->find(1);

            $tokoBranch = Branch::query()->where('work_location_id', $tokoLocation?->id)->first()
                ?? Branch::query()->first();
            $gudangWarehouse = Warehouse::query()->where('work_location_id', $gudangLocation?->id)->first()
                ?? Warehouse::query()->first();

            $tokoBin = WarehouseLocation::query()->where('full_code', 'TKO-DEMO-ZONA-DISPLAY-RAK-DPN')->first();
            $gudangBin = WarehouseLocation::query()->where('full_code', 'GDG-DEMO-ZONA-A-RAK-01-BIN-01')->first();
            $tokoBinScope = $tokoBin ? (string) $tokoBin->id : 'none';
            $gudangBinScope = $gudangBin ? (string) $gudangBin->id : 'none';

            // 5. Definisi 10 Produk Sampel (5 Stok Cukup, 5 Stok Kritis)
            $sampleProducts = [
                // --- 5 PRODUK STOK CUKUP ---
                [
                    'sku' => 'KCA-PRG-001',
                    'name' => 'Piring Makan Kaca Bening 9 Inch',
                    'category_id' => $catKaca->id,
                    'brand_id' => $brandKedaung->id,
                    'supplier' => $supplierKaca,
                    'cost_price' => 8500,
                    'min_price' => 11000,
                    'price' => 12000,
                    'max_price' => 14000,
                    'min_stock' => 20,
                    'safety_stock' => 10,
                    'toko_qty' => 80,
                    'gudang_qty' => 120,
                    'barcode' => '899100100001',
                    'is_critical' => false,
                ],
                [
                    'sku' => 'KCA-MGK-002',
                    'name' => 'Mangkok Kaca Sup Motif Ulir',
                    'category_id' => $catKaca->id,
                    'brand_id' => $brandKedaung->id,
                    'supplier' => $supplierKaca,
                    'cost_price' => 9000,
                    'min_price' => 12000,
                    'price' => 14000,
                    'max_price' => 16000,
                    'min_stock' => 15,
                    'safety_stock' => 10,
                    'toko_qty' => 65,
                    'gudang_qty' => 100,
                    'barcode' => '899100100002',
                    'is_critical' => false,
                ],
                [
                    'sku' => 'PLS-KLK-003',
                    'name' => 'Kotak Bekal Makanan Plastik 1000ml',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandLion->id,
                    'supplier' => $supplierPlastik,
                    'cost_price' => 15000,
                    'min_price' => 19000,
                    'price' => 22000,
                    'max_price' => 25000,
                    'min_stock' => 15,
                    'safety_stock' => 8,
                    'toko_qty' => 50,
                    'gudang_qty' => 80,
                    'barcode' => '899100100003',
                    'is_critical' => false,
                ],
                [
                    'sku' => 'PLS-KRJ-004',
                    'name' => 'Keranjang Plastik Serbaguna M',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandMaspion->id,
                    'supplier' => $supplierPlastik,
                    'cost_price' => 18000,
                    'min_price' => 22000,
                    'price' => 26000,
                    'max_price' => 30000,
                    'min_stock' => 12,
                    'safety_stock' => 6,
                    'toko_qty' => 45,
                    'gudang_qty' => 70,
                    'barcode' => '899100100004',
                    'is_critical' => false,
                ],
                [
                    'sku' => 'PLS-TPL-005',
                    'name' => 'Toples Plastik Kedap Udara 2 Liter',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandLion->id,
                    'supplier' => $supplierPlastik,
                    'cost_price' => 22000,
                    'min_price' => 28000,
                    'price' => 32000,
                    'max_price' => 36000,
                    'min_stock' => 10,
                    'safety_stock' => 5,
                    'toko_qty' => 40,
                    'gudang_qty' => 60,
                    'barcode' => '899100100005',
                    'is_critical' => false,
                ],

                // --- 5 PRODUK STOK KRITIS (Menipis / Kosong) ---
                [
                    'sku' => 'KCA-GLS-006',
                    'name' => 'Gelas Kaca Cangkir Kopi 250ml',
                    'category_id' => $catKaca->id,
                    'brand_id' => $brandKedaung->id,
                    'supplier' => $supplierKaca,
                    'cost_price' => 4500,
                    'min_price' => 6500,
                    'price' => 7500,
                    'max_price' => 9000,
                    'min_stock' => 24,
                    'safety_stock' => 12,
                    'toko_qty' => 4, // Kritis: 4 <= 24
                    'gudang_qty' => 0,
                    'barcode' => '899100100006',
                    'is_critical' => true,
                ],
                [
                    'sku' => 'KCA-TKP-007',
                    'name' => 'Teko Kaca Pitcher 1.5 Liter',
                    'category_id' => $catKaca->id,
                    'brand_id' => $brandKedaung->id,
                    'supplier' => $supplierKaca,
                    'cost_price' => 38000,
                    'min_price' => 48000,
                    'price' => 55000,
                    'max_price' => 62000,
                    'min_stock' => 10,
                    'safety_stock' => 5,
                    'toko_qty' => 2, // Kritis: 2 <= 10
                    'gudang_qty' => 0,
                    'barcode' => '899100100007',
                    'is_critical' => true,
                ],
                [
                    'sku' => 'PLS-BSK-008',
                    'name' => 'Baskom Plastik Cuci Jumbo 40cm',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandMaspion->id,
                    'supplier' => $supplierPlastik,
                    'cost_price' => 24000,
                    'min_price' => 30000,
                    'price' => 35000,
                    'max_price' => 40000,
                    'min_stock' => 15,
                    'safety_stock' => 8,
                    'toko_qty' => 3, // Kritis: 3 <= 15
                    'gudang_qty' => 0,
                    'barcode' => '899100100008',
                    'is_critical' => true,
                ],
                [
                    'sku' => 'PLS-EMB-009',
                    'name' => 'Ember Plastik Tutup 20 Liter',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandLion->id,
                    'supplier' => $supplierPlastik,
                    'cost_price' => 31000,
                    'min_price' => 39000,
                    'price' => 45000,
                    'max_price' => 50000,
                    'min_stock' => 12,
                    'safety_stock' => 6,
                    'toko_qty' => 1, // Kritis: 1 <= 12
                    'gudang_qty' => 0,
                    'barcode' => '899100100009',
                    'is_critical' => true,
                ],
                [
                    'sku' => 'PLS-RPP-010',
                    'name' => 'Rak Piring Plastik Susun 3',
                    'category_id' => $catPlastik->id,
                    'brand_id' => $brandMaspion->id,
                    'supplier' => $supplierDapur,
                    'cost_price' => 52000,
                    'min_price' => 65000,
                    'price' => 75000,
                    'max_price' => 85000,
                    'min_stock' => 8,
                    'safety_stock' => 4,
                    'toko_qty' => 0, // Kritis (Kosong): 0 <= 8
                    'gudang_qty' => 0,
                    'barcode' => '899100100010',
                    'is_critical' => true,
                ],
            ];

            foreach ($sampleProducts as $item) {
                $totalStock = $item['toko_qty'] + $item['gudang_qty'];

                $product = Product::query()->updateOrCreate(
                    ['sku' => $item['sku']],
                    [
                        'name' => $item['name'],
                        'category_id' => $item['category_id'],
                        'brand_id' => $item['brand_id'],
                        'base_unit_id' => $unitPcs->id,
                        'status' => 'active',
                        'minimum_order' => 1,
                        'minimum_stock' => $item['min_stock'],
                        'safety_stock' => $item['safety_stock'],
                        'default_warehouse_id' => $gudangWarehouse?->id,
                        'cost_price' => $item['cost_price'],
                        'minimum_price' => $item['min_price'],
                        'total_stock' => $totalStock,
                        'has_transactions' => true,
                    ],
                );

                // Base Unit
                $baseUnit = ProductUnit::query()->updateOrCreate(
                    ['product_id' => $product->id, 'unit_id' => $unitPcs->id],
                    [
                        'name' => 'Pieces',
                        'conversion_factor' => 1,
                        'is_base' => true,
                        'is_sellable' => true,
                        'is_active' => true,
                    ],
                );

                // Barcode
                ProductBarcode::query()->updateOrCreate(
                    ['code' => $item['barcode']],
                    [
                        'product_id' => $product->id,
                        'product_unit_id' => $baseUnit->id,
                        'type' => 'barcode',
                        'is_primary' => true,
                        'is_active' => true,
                    ],
                );

                // Supplier Product Link
                SupplierProduct::query()->updateOrCreate(
                    ['supplier_id' => $item['supplier']->id, 'product_id' => $product->id],
                    [
                        'last_price' => $item['cost_price'],
                        'last_supplied_at' => now()->subDays(3),
                    ],
                );

                // Product Selling Price (Retail)
                if ($tokoBranch instanceof Branch && $tokoLocation instanceof WorkLocation) {
                    ProductPrice::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'branch_id' => $tokoBranch->id,
                            'channel' => 'retail',
                        ],
                        [
                            'work_location_id' => $tokoLocation->id,
                            'price_ring' => 'Ring Retail',
                            'min_price' => $item['min_price'],
                            'recommended_price' => $item['price'],
                            'max_price' => $item['max_price'],
                            'minimum_qty' => 1,
                            'priority' => 10,
                            'starts_at' => now()->subDays(10)->toDateString(),
                            'status' => ProductPriceStatus::ACTIVE,
                            'notes' => 'Harga retail sampel',
                        ],
                    );
                }

                // 6. Stok di Toko
                if ($tokoLocation instanceof WorkLocation) {
                    $stockToko = Stock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'work_location_id' => $tokoLocation->id,
                            'warehouse_location_id' => $tokoBin?->id,
                        ],
                        [
                            'location_scope_key' => 'work:'.$tokoLocation->id.'|bin:'.$tokoBinScope,
                            'quantity_on_hand' => $item['toko_qty'],
                            'quantity_reserved' => 0,
                            'quantity_damaged' => 0,
                            'cost_value' => bcmul((string) $item['cost_price'], (string) $item['toko_qty'], 2),
                        ],
                    );

                    if ($item['toko_qty'] > 0) {
                        StockBatch::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'work_location_id' => $tokoLocation->id,
                                'batch_no' => 'BATCH-TKO-'.$product->sku,
                            ],
                            [
                                'supplier_id' => $item['supplier']->id,
                                'stock_id' => $stockToko->id,
                                'warehouse_location_id' => $tokoBin?->id,
                                'source_type' => 'opening_stock',
                                'source_id' => 1,
                                'unit_id' => $unitPcs->id,
                                'conversion_factor_snapshot' => 1,
                                'received_at' => now()->subDays(5)->toDateString(),
                                'cost_price' => $item['cost_price'],
                                'entry_sale_price_snapshot' => $item['price'],
                                'quantity_on_hand' => $item['toko_qty'],
                                'quantity_reserved' => 0,
                                'quantity_damaged' => 0,
                                'status' => 'active',
                            ],
                        );

                        $mutToko = StockMutation::query()
                            ->where('reference_no', 'INIT-TKO-'.$product->sku)
                            ->exists();

                        if (! $mutToko) {
                            StockMutation::query()->create([
                                'product_id' => $product->id,
                                'stock_id' => $stockToko->id,
                                'work_location_id' => $tokoLocation->id,
                                'warehouse_location_id' => $tokoBin?->id,
                                'mutation_type' => 'receive',
                                'direction' => 'in',
                                'quantity_on_hand_before' => 0,
                                'quantity_on_hand_change' => $item['toko_qty'],
                                'quantity_on_hand_after' => $item['toko_qty'],
                                'quantity_reserved_before' => 0,
                                'quantity_reserved_change' => 0,
                                'quantity_reserved_after' => 0,
                                'quantity_damaged_before' => 0,
                                'quantity_damaged_change' => 0,
                                'quantity_damaged_after' => 0,
                                'actor_user_id' => $user->id,
                                'unit_id' => $unitPcs->id,
                                'reference_type' => 'opening_stock',
                                'reference_no' => 'INIT-TKO-'.$product->sku,
                                'reason' => 'Saldo awal toko sampel',
                                'occurred_at' => now(),
                            ]);
                        }
                    }
                }

                // 7. Stok di Gudang
                if ($gudangLocation instanceof WorkLocation) {
                    $stockGudang = Stock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'work_location_id' => $gudangLocation->id,
                            'warehouse_location_id' => $gudangBin?->id,
                        ],
                        [
                            'location_scope_key' => 'work:'.$gudangLocation->id.'|bin:'.$gudangBinScope,
                            'quantity_on_hand' => $item['gudang_qty'],
                            'quantity_reserved' => 0,
                            'quantity_damaged' => 0,
                            'cost_value' => bcmul((string) $item['cost_price'], (string) $item['gudang_qty'], 2),
                        ],
                    );

                    if ($item['gudang_qty'] > 0) {
                        StockBatch::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'work_location_id' => $gudangLocation->id,
                                'batch_no' => 'BATCH-GDG-'.$product->sku,
                            ],
                            [
                                'supplier_id' => $item['supplier']->id,
                                'stock_id' => $stockGudang->id,
                                'warehouse_location_id' => $gudangBin?->id,
                                'source_type' => 'opening_stock',
                                'source_id' => 1,
                                'unit_id' => $unitPcs->id,
                                'conversion_factor_snapshot' => 1,
                                'received_at' => now()->subDays(7)->toDateString(),
                                'cost_price' => $item['cost_price'],
                                'entry_sale_price_snapshot' => $item['price'],
                                'quantity_on_hand' => $item['gudang_qty'],
                                'quantity_reserved' => 0,
                                'quantity_damaged' => 0,
                                'status' => 'active',
                            ],
                        );

                        $mutGudang = StockMutation::query()
                            ->where('reference_no', 'INIT-GDG-'.$product->sku)
                            ->exists();

                        if (! $mutGudang) {
                            StockMutation::query()->create([
                                'product_id' => $product->id,
                                'stock_id' => $stockGudang->id,
                                'work_location_id' => $gudangLocation->id,
                                'warehouse_location_id' => $gudangBin?->id,
                                'mutation_type' => 'receive',
                                'direction' => 'in',
                                'quantity_on_hand_before' => 0,
                                'quantity_on_hand_change' => $item['gudang_qty'],
                                'quantity_on_hand_after' => $item['gudang_qty'],
                                'quantity_reserved_before' => 0,
                                'quantity_reserved_change' => 0,
                                'quantity_reserved_after' => 0,
                                'quantity_damaged_before' => 0,
                                'quantity_damaged_change' => 0,
                                'quantity_damaged_after' => 0,
                                'actor_user_id' => $user->id,
                                'unit_id' => $unitPcs->id,
                                'reference_type' => 'opening_stock',
                                'reference_no' => 'INIT-GDG-'.$product->sku,
                                'reason' => 'Saldo awal gudang sampel',
                                'occurred_at' => now(),
                            ]);
                        }
                    }
                }
            }
        });
    }
}
