<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMutationType;
use App\Enums\StockOpnameStatus;
use App\Enums\StockTransferStatus;
use App\Enums\InventoryLossStatus;
use App\Enums\RestockRequestStatus;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryLoss;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCostHistory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiptQcResult;
use App\Models\RestockRequest;
use App\Models\RestockRequestItem;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierScore;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Seeder khusus modul gudang (warehouse).
 *
 * Mengisi data operasional gudang sehingga dashboard, laporan,
 * dan halaman gudang menampilkan data realistis untuk latihan/ujian:
 *
 *   - Gudang + work location + struktur zona/rak/bin
 *   - User kepala gudang, staff gudang, purchasing, picker/packer (terhubung ke work location gudang)
 *   - Master produk (kategori, brand, satuan, barcode, supplier product)
 *   - Saldo awal stok via InventoryService (movement mutations otomatis)
 *   - Purchase order + goods receipt (posted) + HPP history + supplier score
 *   - Restock request + stock transfer (pending_approval) agar pipeline terlihat
 *   - Stock opname (counting) + inventory loss (pending approval)
 *
 * Aman dijalankan berulang (semua create/update menggunakan updateOrCreate).
 */
class WarehouseSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, array{name: string, username: string, email: string, roles: list<string>}> */
    private array $warehouseAccounts = [
        'kepala_gudang' => ['name' => 'Kepala Gudang', 'username' => 'kepala-gudang', 'email' => 'kepala-gudang@gudangtoko.test', 'roles' => ['kepala_gudang']],
        'staff_gudang' => ['name' => 'Staff Gudang', 'username' => 'staff-gudang', 'email' => 'staff-gudang@gudangtoko.test', 'roles' => ['staff_gudang']],
        'purchasing' => ['name' => 'Purchasing', 'username' => 'purchasing', 'email' => 'purchasing@gudangtoko.test', 'roles' => ['purchasing']],
        'picker_packer' => ['name' => 'Picker Packer', 'username' => 'picker-packer', 'email' => 'picker-packer@gudangtoko.test', 'roles' => ['picker_packer']],
    ];

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Warehouse seeder hanya boleh dijalankan pada environment local/testing.');

        // Pastikan role & permission dasar sudah ada.
        $this->call(RolePermissionSeeder::class);

        DB::transaction(function (): void {
            $sync = app(WorkLocationSyncService::class);

            // 1) Gudang + struktur lokasi
            [$warehouse, $warehouseLocation] = $this->seedWarehouse($sync);

            // 2) User khusus gudang (terhubung ke work location gudang)
            $users = $this->seedWarehouseUsers($warehouseLocation);

            // 3) Master data pendukung (unit, kategori, brand, supplier)
            [$unitPcs, $unitPack] = $this->seedUnits();
            [$category, $brand] = $this->seedTaxonomy();
            $supplier = $this->seedSupplier();

            // 4) Produk + product unit + barcode + supplier product
            $products = $this->seedProducts($warehouse, $category, $brand, $unitPcs, $unitPack, $supplier);

            // 5) Zona, rak, dan bin di gudang
            [$zone, $rack, $bin] = $this->seedWarehouseStructure($warehouse);

            // 6) Saldo awal gudang (mutasi receive otomatis melalui InventoryService)
            $this->seedOpeningStock($products, $warehouseLocation, $bin, $users['staff_gudang']);

            // 7) Beberapa PO yang sudah diterima sebagian + receipt yang sudah posted
            $this->seedPurchasingAndReceipt($warehouse, $supplier, $products[0], $unitPcs, $bin, $users);

            // 8) Restock request + stock transfer yang masih menunggu approval
            $this->seedRestockAndTransfer($warehouse, $warehouseLocation, $bin, $products[1], $unitPcs, $users);

            // 9) Stock opname yang sedang berjalan + inventory loss
            $this->seedOpnameAndLoss($warehouseLocation, $bin, $products[0], $users);

            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /** @return array{0: Warehouse, 1: WorkLocation} */
    private function getOrCreateBranch(Warehouse $warehouse): Branch
    {
        return Branch::query()->updateOrCreate(
            ['code' => 'TKO-GDG-DEMO'],
            [
                'primary_warehouse_id' => $warehouse->id,
                'name' => 'Toko Gudang Demo',
                'address' => 'Jl. Gudang Toko Demo No. 1',
                'phone_number' => '021-7700102',
                'sales_target' => 25000000,
                'price_configuration' => 'standard',
                'closing_configuration' => 'daily',
                'is_closing_required' => true,
                'is_active' => true,
            ],
        );
    }

    /** @return array{0: Warehouse, 1: WorkLocation} */
    private function seedWarehouse(WorkLocationSyncService $sync): array
    {
        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-JBU-AIR'],
            [
                'name' => 'Gudang Jambu Air',
                'address' => 'Jl. Jambu Air No. 12, Pondok Bambu',
                'city' => 'Jakarta',
                'phone_number' => '021-7700101',
                'capacity' => 8000,
                'service_area' => 'Jakarta Timur',
                'is_active' => true,
            ],
        );

        $warehouseLocation = $sync->syncWarehouse($warehouse);

        return [$warehouse, $warehouseLocation];
    }

    /** @return array<string, User> */
    private function seedWarehouseUsers(WorkLocation $warehouseLocation): array
    {
        $users = [];

        foreach ($this->warehouseAccounts as $key => $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'phone_number' => '628'.str_pad((string) (200000000 + count($users)), 9, '0', STR_PAD_LEFT),
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles(array_map(
                static fn (string $roleName): Role => Role::findOrCreate($roleName),
                $account['roles'],
            ));

            // Ikat user ke lokasi kerja gudang.
            $user->workLocations()->syncWithoutDetaching([
                $warehouseLocation->id => ['is_default' => true, 'is_active' => true],
            ]);

            $users[$key] = $user;
        }

        // Employee record untuk picker/packer (untuk assignment opname/transfer).
        if (isset($users['picker_packer'])) {
            Employee::query()->updateOrCreate(
                ['user_id' => $users['picker_packer']->id],
                [
                    'employee_no' => 'EMP-GDG-001',
                    'user_id' => $users['picker_packer']->id,
                    'work_location_id' => $warehouseLocation->id,
                    'name' => $users['picker_packer']->name,
                    'position' => 'Picker / Packer',
                    'whatsapp_number' => $users['picker_packer']->phone_number,
                    'joined_at' => now()->subMonths(2)->toDateString(),
                    'status' => 'active',
                    'is_active' => true,
                ],
            );
        }

        return $users;
    }

    /** @return array{0: Unit, 1: Unit} */
    private function seedUnits(): array
    {
        $pcs = Unit::query()->updateOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'pcs', 'precision' => 0, 'is_active' => true]);
        $pack = Unit::query()->updateOrCreate(['code' => 'PACK'], ['name' => 'Pack', 'symbol' => 'pack', 'precision' => 0, 'is_active' => true]);

        return [$pcs, $pack];
    }

    /** @return array{0: ProductCategory, 1: ProductBrand} */
    private function seedTaxonomy(): array
    {
        $category = ProductCategory::query()->updateOrCreate(
            ['code' => 'GDG-FASHION'],
            ['name' => 'Fashion Gudang', 'sort_order' => 20, 'icon' => 'ki-outline ki-shirt', 'is_active' => true],
        );
        $brand = ProductBrand::query()->updateOrCreate(
            ['code' => 'GDG-BRAND'],
            ['name' => 'Brand Gudang', 'description' => 'Brand khusus data gudang untuk latihan.', 'is_active' => true],
        );

        return [$category, $brand];
    }

    private function seedSupplier(): Supplier
    {
        return Supplier::query()->updateOrCreate(
            ['code' => 'SUP-GDG-001'],
            [
                'name' => 'Supplier Utama Gudang',
                'contact_name' => 'Budi Supplier',
                'whatsapp_number' => '628131313131',
                'email' => 'supplier.gudang@gudangtoko.test',
                'address' => 'Jl. Supplier Gudang No. 9',
                'city' => 'Bekasi',
                'payment_term_days' => 30,
                'last_price' => 30000,
                'performance_score' => 90,
                'is_active' => true,
            ],
        );
    }

    /** @return list<Product> */
    private function seedProducts(Warehouse $warehouse, ProductCategory $category, ProductBrand $brand, Unit $pcs, Unit $pack, Supplier $supplier): array
    {
        $rows = [
            ['sku' => 'GDG-CAP-001', 'name' => 'Topi Gudang Snapback', 'cost' => 22000, 'min' => 30000, 'price' => 42000, 'barcode' => '899911100001'],
            ['sku' => 'GDG-BAG-001', 'name' => 'Tas Gudang Kanvas', 'cost' => 58000, 'min' => 72000, 'price' => 95000, 'barcode' => '899911100002'],
            ['sku' => 'GDG-TSHIRT-001', 'name' => 'Kaos Gudang Hitam', 'cost' => 38000, 'min' => 48000, 'price' => 62000, 'barcode' => '899911100003'],
            ['sku' => 'GDG-JKT-001', 'name' => 'Jaket Gudang Hoodie', 'cost' => 95000, 'min' => 120000, 'price' => 155000, 'barcode' => '899911100004'],
            ['sku' => 'GDG-SOCK-001', 'name' => 'Kaos Kaki Gudang Sport', 'cost' => 12000, 'min' => 16000, 'price' => 24000, 'barcode' => '899911100005'],
        ];

        $products = [];
        foreach ($rows as $row) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'base_unit_id' => $pcs->id,
                    'status' => 'active',
                    'minimum_order' => 1,
                    'minimum_stock' => 10,
                    'safety_stock' => 5,
                    'default_warehouse_id' => $warehouse->id,
                    'cost_price' => $row['cost'],
                    'minimum_price' => $row['min'],
                ],
            );

            $baseUnit = ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $pcs->id],
                ['name' => 'Pieces', 'conversion_factor' => 1, 'is_base' => true, 'is_sellable' => true, 'is_active' => true],
            );
            ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $pack->id],
                ['name' => 'Pack isi 12', 'conversion_factor' => 12, 'is_base' => false, 'is_sellable' => true, 'is_active' => true],
            );
            ProductBarcode::query()->updateOrCreate(
                ['code' => $row['barcode']],
                ['product_id' => $product->id, 'product_unit_id' => $baseUnit->id, 'type' => 'barcode', 'is_primary' => true, 'is_active' => true],
            );
            SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                ['last_price' => $row['cost'], 'last_supplied_at' => now()],
            );

            $products[] = $product;
        }

        return $products;
    }

    /** @return array{0: WarehouseLocation, 1: WarehouseLocation, 2: WarehouseLocation} */
    private function seedWarehouseStructure(Warehouse $warehouse): array
    {
        $zone = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => null, 'type' => 'zone', 'code' => 'ZONA-A', 'name' => 'Zona A Fashion', 'capacity' => 4000, 'item_type' => 'Fashion', 'is_active' => true],
        );

        $rack = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-01'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $zone->id, 'type' => 'rack', 'code' => 'RAK-01', 'name' => 'Rak 01 Display', 'capacity' => 800, 'item_type' => 'Fashion', 'is_active' => true],
        );

        $rack2 = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-02'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $zone->id, 'type' => 'rack', 'code' => 'RAK-02', 'name' => 'Rak 02 Top Up', 'capacity' => 600, 'item_type' => 'Fashion', 'is_active' => true, 'full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-02'],
        );

        WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-01-BIN-01'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-RAK01-TP', 'name' => 'Bin 01 Topi', 'capacity' => 200, 'item_type' => 'Topi', 'is_active' => true],
        );

        WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-01-BIN-02'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-RAK01-TS', 'name' => 'Bin 02 Tas', 'capacity' => 150, 'item_type' => 'Tas', 'is_active' => true],
        );

        WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-01-BIN-03'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-RAK01-KS', 'name' => 'Bin 03 Kaos', 'capacity' => 250, 'item_type' => 'Kaos', 'is_active' => true],
        );

        WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-02-BIN-01'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack2->id, 'type' => 'bin', 'code' => 'BIN-RAK02-JK', 'name' => 'Bin 01 Jaket', 'capacity' => 80, 'item_type' => 'Jaket', 'is_active' => true],
        );

        WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-02-BIN-02'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack2->id, 'type' => 'bin', 'code' => 'BIN-RAK02-AK', 'name' => 'Bin 02 Aksesoris', 'capacity' => 300, 'item_type' => 'Aksesoris', 'is_active' => true],
        );

        // Bin utama yang dipakai untuk stocking awal & opname demo.
        $mainBin = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-JBU-AIR-ZONA-A-RAK-01-BIN-MAIN'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-MAIN', 'name' => 'Bin Utama Gudang', 'capacity' => 500, 'item_type' => 'Campuran', 'is_active' => true],
        );

        return [$zone, $rack, $mainBin];
    }

    /** @param list<Product> $products */
    private function seedOpeningStock(array $products, WorkLocation $warehouseLocation, WarehouseLocation $bin, User $actor): void
    {
        $inventory = app(InventoryService::class);

        // Pola qty buka: ada yang tinggi, ada yang rendah → memicu KPI "stok kritis" & "stok kosong".
        $pattern = [200, 150, 180, 8, 0];

        foreach ($products as $index => $product) {
            $qty = $pattern[$index] ?? 100;

            if ($qty <= 0) {
                // Produk sengaja kosong untuk menampilkan "Stok Kosong" di dashboard.
                continue;
            }

            $inventory->receive(
                product: $product,
                workLocation: $warehouseLocation,
                warehouseLocation: $bin,
                quantity: (string) $qty,
                actor: $actor,
                reference: ['type' => 'warehouse_seed', 'no' => 'GDG-OPENING-'.($index + 1)],
                reason: 'Saldo pembuka gudang (warehouse seeder).',
                idempotencyKey: 'warehouse-opening-'.$product->sku,
            );

            // Catat stock batch sederhana untuk kartu stok & FIFO tracking.
            $stock = Stock::query()
                ->where('product_id', $product->id)
                ->where('work_location_id', $warehouseLocation->id)
                ->first();

            StockBatch::query()->updateOrCreate(
                ['product_id' => $product->id, 'batch_no' => 'GDG-BATCH-'.$product->sku],
                [
                    'stock_id' => $stock?->id,
                    'received_at' => now()->subDays(7)->toDateString(),
                    'expires_at' => now()->addYear()->toDateString(),
                    'cost_price' => $product->cost_price,
                    'quantity_on_hand' => $qty,
                    'quantity_reserved' => 0,
                    'status' => 'active',
                ],
            );
        }
    }

    /** @param array<string, User> $users */
    private function seedPurchasingAndReceipt(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit, WarehouseLocation $bin, array $users): void
    {
        $po = PurchaseOrder::query()->updateOrCreate(
            ['number' => 'PO-GDG-0001'],
            [
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'order_date' => now()->subDays(10)->toDateString(),
                'expected_at' => now()->subDays(2)->toDateString(),
                'payment_term_days' => 30,
                'notes' => 'PO rutin gudang.',
                'status' => PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
                'created_by' => $users['purchasing']->id,
                'submitted_at' => now()->subDays(10),
                'approved_at' => now()->subDays(9),
                'approved_by' => $users['kepala_gudang']->id,
                'items_subtotal' => 760000,
                'header_discount' => 0,
                'freight_cost' => 20000,
                'additional_cost' => 0,
                'grand_total' => 780000,
            ],
        );

        $poItem = PurchaseOrderItem::query()->updateOrCreate(
            ['purchase_order_id' => $po->id, 'product_id' => $product->id],
            [
                'unit_id' => $unit->id,
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'unit_name_snapshot' => $unit->name,
                'conversion_factor_snapshot' => 1,
                'quantity_ordered' => 20,
                'quantity_received' => 15,
                'unit_price' => $product->cost_price,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'subtotal' => 760000,
            ],
        );

        $receipt = GoodsReceipt::query()->updateOrCreate(
            ['number' => 'RCV-GDG-0001'],
            [
                'purchase_order_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'received_at' => now()->subDays(2)->toDateString(),
                'delivery_note_number' => 'SJ-GDG-0001',
                'received_by' => $users['staff_gudang']->id,
                'status' => 'posted',
                'posted_at' => now()->subDays(2),
                'posted_by' => $users['kepala_gudang']->id,
                'actual_freight_cost' => 20000,
                'actual_additional_cost' => 0,
                'notes' => 'Penerimaan parsial PO gudang.',
            ],
        );

        $hppAfter = bcadd((string) $product->cost_price, '500.00', 2);

        $receiptItem = GoodsReceiptItem::query()->updateOrCreate(
            ['goods_receipt_id' => $receipt->id, 'product_id' => $product->id],
            [
                'purchase_order_item_id' => $poItem->id,
                'unit_id' => $unit->id,
                'warehouse_location_id' => $bin->id,
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'unit_name_snapshot' => $unit->name,
                'conversion_factor_snapshot' => 1,
                'quantity_ordered' => 20,
                'previously_received' => 0,
                'outstanding_before' => 20,
                'quantity_received' => 15,
                'quantity_accepted' => 14,
                'quantity_rejected' => 1,
                'quantity_damaged' => 0,
                'unit_price' => $product->cost_price,
                'landed_cost_allocated' => 10000,
                'batch_no' => 'RCV-BATCH-GDG-001',
                'hpp_before' => $product->cost_price,
                'incoming_cost' => $product->cost_price,
                'hpp_after' => $hppAfter,
            ],
        );

        ReceiptQcResult::query()->updateOrCreate(
            ['goods_receipt_item_id' => $receiptItem->id, 'qc_status' => 'accepted'],
            ['quantity' => 14, 'reason' => 'Barang sesuai spesifikasi.'],
        );

        ProductCostHistory::query()->updateOrCreate(
            ['product_id' => $product->id, 'goods_receipt_id' => $receipt->id],
            [
                'supplier_id' => $supplier->id,
                'goods_receipt_item_id' => $receiptItem->id,
                'method' => 'moving_average',
                'qty_before' => 200,
                'qty_incoming' => 14,
                'qty_after' => 214,
                'hpp_before' => $product->cost_price,
                'incoming_cost' => $product->cost_price,
                'landed_cost_allocated' => 10000,
                'hpp_after' => $hppAfter,
                'effective_at' => now()->subDays(2),
            ],
        );

        SupplierScore::query()->updateOrCreate(
            ['supplier_id' => $supplier->id, 'goods_receipt_id' => $receipt->id],
            [
                'quantity_received' => 15,
                'quantity_accepted' => 14,
                'quantity_rejected' => 1,
                'quantity_damaged' => 0,
                'quality_score' => 90,
                'delivery_score' => 92,
                'price_score' => 88,
                'total_score' => 90,
                'received_at' => now()->subDays(2)->toDateString(),
            ],
        );
    }

    /** @param array<string, User> $users */
    private function seedRestockAndTransfer(Warehouse $warehouse, WorkLocation $warehouseLocation, WarehouseLocation $bin, Product $product, Unit $unit, array $users): void
    {
        $restock = RestockRequest::query()->updateOrCreate(
            ['number' => 'RST-GDG-0001'],
            [
                'branch_id' => $this->getOrCreateBranch($warehouse)->id,
                'source_warehouse_id' => $warehouse->id,
                'requested_by' => $users['staff_gudang']->id,
                'approved_by' => null,
                'status' => RestockRequestStatus::PENDING_APPROVAL->value,
                'priority' => 'high',
                'needed_at' => now()->addDays(2)->toDateString(),
                'submitted_at' => now()->subDay(),
                'notes' => 'Permintaan stok dari pick face ke buffer gudang.',
            ],
        );

        RestockRequestItem::query()->updateOrCreate(
            ['restock_request_id' => $restock->id, 'product_id' => $product->id],
            [
                'quantity_requested' => 30,
                'quantity_approved' => 0,
                'priority' => 'high',
                'notes' => 'Top up buffer.',
            ],
        );

        StockTransfer::query()->updateOrCreate(
            ['number' => 'TRF-GDG-0001'],
            [
                'restock_request_id' => $restock->id,
                'source_work_location_id' => $warehouseLocation->id,
                'source_warehouse_location_id' => $bin->id,
                'destination_work_location_id' => $warehouseLocation->id,
                'destination_warehouse_location_id' => $bin->id,
                'requested_by' => $users['staff_gudang']->id,
                'status' => StockTransferStatus::PENDING_APPROVAL->value,
                'transfer_date' => now()->toDateString(),
                'submitted_at' => now()->subDay(),
                'notes' => 'Antrian transfer internal gudang menunggu approval kepala gudang.',
            ],
        );


        // Item transfer actual (gunakan restock id = transfer id sudah cukup melalui relasi langsung).
        $transfer = StockTransfer::query()->where('number', 'TRF-GDG-0001')->first();
        if ($transfer) {
            StockTransferItem::query()->updateOrCreate(
                ['stock_transfer_id' => $transfer->id, 'product_id' => $product->id],
                [
                    'unit_id' => $unit->id,
                    'source_warehouse_location_id' => $bin->id,
                    'destination_warehouse_location_id' => $bin->id,
                    'product_sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'unit_name_snapshot' => $unit->name,
                    'conversion_factor_snapshot' => 1,
                    'quantity_requested' => 30,
                    'quantity_approved' => 0,
                    'quantity_reserved' => 0,
                    'quantity_picked' => 0,
                    'quantity_short' => 0,
                    'quantity_shipped' => 0,
                    'quantity_received' => 0,
                    'quantity_damaged' => 0,
                    'quantity_discrepancy' => 0,
                    'notes' => 'Menunggu approval.',
                ],
            );
        }
    }

    /** @param array<string, User> $users */
    private function seedOpnameAndLoss(WorkLocation $warehouseLocation, WarehouseLocation $bin, Product $product, array $users): void
    {
        $stock = Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $warehouseLocation->id)
            ->first();

        $opname = StockOpname::query()->updateOrCreate(
            ['number' => 'OPN-GDG-0001'],
            [
                'work_location_id' => $warehouseLocation->id,
                'warehouse_location_id' => $bin->id,
                'pic_user_id' => $users['kepala_gudang']->id,
                'created_by' => $users['staff_gudang']->id,
                'status' => StockOpnameStatus::COUNTING->value,
                'method' => 'cycle_count',
                'freeze_stock' => false,
                'blind_count' => false,
                'requires_owner_approval' => false,
                'scheduled_at' => now()->toDateString(),
                'started_at' => now(),
                'threshold_qty' => 5,
                'threshold_value' => 250000,
                'notes' => 'Opname rutin mingguan gudang.',
            ],
        );

        if ($stock) {
            StockOpnameItem::query()->updateOrCreate(
                ['stock_opname_id' => $opname->id, 'product_id' => $product->id],
                [
                    'stock_id' => $stock->id,
                    'warehouse_location_id' => $bin->id,
                    'counter_user_id' => $users['staff_gudang']->id,
                    'product_sku_snapshot' => $product->sku,
                    'product_name_snapshot' => $product->name,
                    'system_qty_snapshot' => $stock->quantity_on_hand,
                    'counted_qty' => null,
                    'difference_qty' => 0,
                    'unit_cost' => $product->cost_price,
                    'estimated_value' => 0,
                    'reason' => 'other',
                    'note' => 'Belum dihitung.',
                ],
            );
        }

        InventoryLoss::query()->updateOrCreate(
            ['number' => 'LOSS-GDG-0001'],
            [
                'work_location_id' => $warehouseLocation->id,
                'warehouse_location_id' => $bin->id,
                'product_id' => $product->id,
                'reported_by' => $users['staff_gudang']->id,
                'loss_type' => 'damaged',
                'disposition' => 'move_to_damaged',
                'status' => InventoryLossStatus::PENDING_APPROVAL->value,
                'quantity' => 3,
                'unit_cost_snapshot' => $product->cost_price,
                'loss_value' => bcmul((string) $product->cost_price, '3', 2),
                'reason' => 'Beberapa unit rusak saat bongkar muat.',
                'reported_at' => now(),
            ],
        );
    }
}