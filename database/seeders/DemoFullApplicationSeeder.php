<?php

namespace Database\Seeders;

use App\Models\AnomalyAlert;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\B2bComplaint;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryLoss;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCostHistory;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\ReceiptQcResult;
use App\Models\Receivable;
use App\Models\ReceivableEntry;
use App\Models\ReportExport;
use App\Models\RestockRequest;
use App\Models\RestockRequestItem;
use App\Models\SalePayment;
use App\Models\Shipment;
use App\Models\ShipmentItem;
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
use App\Services\Control\ApprovalWorkflowService;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\WorkLocationSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoFullApplicationSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, array{name: string, username: string, email: string, roles: list<string>, location: string|null}> */
    private array $accounts = [
        'owner' => ['name' => 'Owner', 'username' => 'owner', 'email' => 'owner@gudangtoko.test', 'roles' => ['owner_approver'], 'location' => null],
        'super_admin' => ['name' => 'Super Admin', 'username' => 'superadmin', 'email' => 'superadmin@gudangtoko.test', 'roles' => ['super_admin'], 'location' => null],
        'manajemen_gudang' => ['name' => 'Manajemen Gudang', 'username' => 'manajemen-gudang', 'email' => 'manajemen-gudang@gudangtoko.test', 'roles' => ['kepala_gudang', 'purchasing'], 'location' => 'warehouse'],
        'staff_gudang' => ['name' => 'Staff Gudang', 'username' => 'staff-gudang', 'email' => 'staff-gudang@gudangtoko.test', 'roles' => ['staff_gudang'], 'location' => 'warehouse'],
        'toko_internal' => ['name' => 'Toko Internal', 'username' => 'toko-internal', 'email' => 'toko@gudangtoko.test', 'roles' => ['kepala_toko'], 'location' => 'branch'],
        'kasir_kepala_toko' => ['name' => 'Kasir / Kepala Toko', 'username' => 'kasir', 'email' => 'kasir@gudangtoko.test', 'roles' => ['kasir', 'kepala_toko'], 'location' => 'branch'],
        'langganan_b2b' => ['name' => 'Langganan / B2B', 'username' => 'langganan-b2b', 'email' => 'langganan-b2b@gudangtoko.test', 'roles' => ['langganan_owner'], 'location' => null],
        'akun_pelanggan' => ['name' => 'Akun Pelanggan', 'username' => 'pelanggan', 'email' => 'pelanggan@gudangtoko.test', 'roles' => ['langganan_staff'], 'location' => null],
    ];

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Demo seeder hanya boleh dijalankan pada environment local/testing.');

        DB::transaction(function (): void {
            $this->call(RolePermissionSeeder::class);

            $sync = app(WorkLocationSyncService::class);
            [$warehouse, $warehouseLocation, $branch, $branchLocation] = $this->seedOrganization($sync);
            $users = $this->seedUsers($warehouseLocation, $branchLocation);
            [$unitPcs, $unitPack] = $this->seedUnits();
            [$category, $brand] = $this->seedProductTaxonomy();
            $supplier = $this->seedSupplier();
            $products = $this->seedProducts($warehouse, $category, $brand, $unitPcs, $unitPack, $supplier);
            $bin = $this->seedWarehouseBins($warehouse);
            $customer = $this->seedCustomer($users);

            $this->seedInventory($products, $warehouseLocation, $branchLocation, $bin, $users['staff_gudang']);
            $this->seedPricing($products, $branch);
            $this->seedPurchasingAndReceipt($warehouse, $supplier, $products[0], $unitPcs, $bin, $users);
            $this->seedRestockAndTransfer($warehouse, $branch, $warehouseLocation, $branchLocation, $bin, $products[1], $unitPcs, $users);
            $this->seedStockOpnameAndLoss($warehouseLocation, $bin, $products[0], $users);
            $this->seedRetail($branch, $branchLocation, $bin, $products[0], $unitPcs, $customer, $users);
            $this->seedB2bInvoiceShipmentReceivable($warehouseLocation, $products[1], $unitPcs, $customer, $users);
            $this->seedControlReports($users);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /** @return array{0: Warehouse, 1: WorkLocation, 2: Branch, 3: WorkLocation} */
    private function seedOrganization(WorkLocationSyncService $sync): array
    {
        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-DEMO'],
            [
                'name' => 'Gudang Demo Utama',
                'address' => 'Jl. Demo Gudang No. 10',
                'city' => 'Jakarta',
                'phone_number' => '021-7700001',
                'capacity' => 50000,
                'service_area' => 'Jabodetabek',
                'is_active' => true,
            ],
        );
        $warehouseLocation = $sync->syncWarehouse($warehouse);

        $branch = Branch::query()->updateOrCreate(
            ['code' => 'TKO-DEMO'],
            [
                'primary_warehouse_id' => $warehouse->id,
                'name' => 'Toko Demo Pusat',
                'address' => 'Jl. Demo Toko No. 20',
                'phone_number' => '021-7700002',
                'sales_target' => 75000000,
                'price_configuration' => 'standard',
                'closing_configuration' => 'daily',
                'is_closing_required' => true,
                'is_active' => true,
            ],
        );
        $branchLocation = $sync->syncBranch($branch);

        return [$warehouse, $warehouseLocation, $branch, $branchLocation];
    }

    /** @return array<string, User> */
    private function seedUsers(WorkLocation $warehouseLocation, WorkLocation $branchLocation): array
    {
        $users = [];

        foreach ($this->accounts as $accountKey => $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'username' => $account['username'],
                    'phone_number' => '628'.str_pad((string) (100000000 + count($users)), 9, '0', STR_PAD_LEFT),
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles(array_map(
                static fn (string $roleName): Role => Role::findOrCreate($roleName),
                $account['roles'],
            ));

            if ($account['location'] === 'warehouse') {
                $user->workLocations()->syncWithoutDetaching([$warehouseLocation->id => ['is_default' => true, 'is_active' => true]]);
            }

            if ($account['location'] === 'branch') {
                $user->workLocations()->syncWithoutDetaching([$branchLocation->id => ['is_default' => true, 'is_active' => true]]);
            }

            $users[$accountKey] = $user;
        }

        $this->deactivateLegacySeedAccounts();

        Employee::query()->updateOrCreate(
            ['user_id' => $users['kasir_kepala_toko']->id],
            [
                'employee_no' => 'EMP-TKO-002',
                'user_id' => $users['kasir_kepala_toko']->id,
                'work_location_id' => $branchLocation->id,
                'name' => $users['kasir_kepala_toko']->name,
                'position' => 'Kasir Demo',
                'whatsapp_number' => $users['kasir_kepala_toko']->phone_number,
                'joined_at' => now()->subMonth()->toDateString(),
                'status' => 'active',
                'is_active' => true,
            ],
        );

        return $users;
    }

    private function deactivateLegacySeedAccounts(): void
    {
        User::query()
            ->whereIn('email', [
                'admin@gudangtoko.test',
                'approver@gudangtoko.test',
                'gudang@gudangtoko.test',
                'purchasing@gudangtoko.test',
                'retail@gudangtoko.test',
                'adminconfig@gudangtoko.test',
                'adminuser@gudangtoko.test',
                'b2bowner@gudangtoko.test',
                'b2bstaff@gudangtoko.test',
                'super_admin@gudangtoko.test',
                'owner_viewer@gudangtoko.test',
                'owner_approver@gudangtoko.test',
                'admin_user@gudangtoko.test',
                'admin_config@gudangtoko.test',
                'kepala_gudang@gudangtoko.test',
                'staff_gudang@gudangtoko.test',
                'picker_packer@gudangtoko.test',
                'kepala_toko@gudangtoko.test',
                'supervisor_shift@gudangtoko.test',
                'langganan_owner@gudangtoko.test',
                'langganan_staff@gudangtoko.test',
            ])
            ->update(['is_active' => false]);
    }

    /** @return array{0: Unit, 1: Unit} */
    private function seedUnits(): array
    {
        $pcs = Unit::query()->updateOrCreate(['code' => 'PCS'], ['name' => 'Pieces', 'symbol' => 'pcs', 'precision' => 0, 'is_active' => true]);
        $pack = Unit::query()->updateOrCreate(['code' => 'PACK'], ['name' => 'Pack', 'symbol' => 'pack', 'precision' => 0, 'is_active' => true]);
        Unit::query()->updateOrCreate(['code' => 'DUS'], ['name' => 'Dus', 'symbol' => 'dus', 'precision' => 0, 'is_active' => true]);

        return [$pcs, $pack];
    }

    /** @return array{0: ProductCategory, 1: ProductBrand} */
    private function seedProductTaxonomy(): array
    {
        $category = ProductCategory::query()->updateOrCreate(['code' => 'DEMO-FASHION'], ['name' => 'Fashion Demo', 'sort_order' => 10, 'icon' => 'ki-outline ki-shirt', 'is_active' => true]);
        $brand = ProductBrand::query()->updateOrCreate(['code' => 'DEMO-BRAND'], ['name' => 'Demo Brand', 'description' => 'Brand demo lokal/testing.', 'is_active' => true]);

        return [$category, $brand];
    }

    private function seedSupplier(): Supplier
    {
        return Supplier::query()->updateOrCreate(
            ['code' => 'SUP-DEMO-FULL'],
            [
                'name' => 'Supplier Demo Lengkap',
                'contact_name' => 'Andi Supplier',
                'whatsapp_number' => '628111111111',
                'email' => 'supplier.demo@gudangtoko.test',
                'address' => 'Jl. Supplier Demo No. 5',
                'city' => 'Bandung',
                'payment_term_days' => 30,
                'last_price' => 25000,
                'performance_score' => 88,
                'is_active' => true,
            ],
        );
    }

    /** @return list<Product> */
    private function seedProducts(Warehouse $warehouse, ProductCategory $category, ProductBrand $brand, Unit $pcs, Unit $pack, Supplier $supplier): array
    {
        $rows = [
            ['sku' => 'DEMO-TSHIRT-001', 'name' => 'Kaos Demo Hitam', 'cost' => 35000, 'min' => 45000, 'price' => 59000, 'barcode' => '899900100001'],
            ['sku' => 'DEMO-BAG-001', 'name' => 'Tas Demo Kanvas', 'cost' => 55000, 'min' => 70000, 'price' => 89000, 'barcode' => '899900100002'],
            ['sku' => 'DEMO-CAP-001', 'name' => 'Topi Demo', 'cost' => 20000, 'min' => 28000, 'price' => 39000, 'barcode' => '899900100003'],
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

            $baseUnit = ProductUnit::query()->updateOrCreate(['product_id' => $product->id, 'unit_id' => $pcs->id], ['name' => 'Pieces', 'conversion_factor' => 1, 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);
            ProductUnit::query()->updateOrCreate(['product_id' => $product->id, 'unit_id' => $pack->id], ['name' => 'Pack isi 12', 'conversion_factor' => 12, 'is_base' => false, 'is_sellable' => true, 'is_active' => true]);
            ProductBarcode::query()->updateOrCreate(['code' => $row['barcode']], ['product_id' => $product->id, 'product_unit_id' => $baseUnit->id, 'type' => 'barcode', 'is_primary' => true, 'is_active' => true]);
            SupplierProduct::query()->updateOrCreate(['supplier_id' => $supplier->id, 'product_id' => $product->id], ['last_price' => $row['cost'], 'last_supplied_at' => now()]);

            $products[] = $product;
        }

        return $products;
    }

    private function seedWarehouseBins(Warehouse $warehouse): WarehouseLocation
    {
        $zone = WarehouseLocation::query()->updateOrCreate(['full_code' => 'GDG-DEMO-ZONA-A'], ['warehouse_id' => $warehouse->id, 'parent_id' => null, 'type' => 'zone', 'code' => 'ZONA-A', 'name' => 'Zona A Demo', 'capacity' => 5000, 'item_type' => 'Fashion', 'is_active' => true]);
        $rack = WarehouseLocation::query()->updateOrCreate(['full_code' => 'GDG-DEMO-ZONA-A-RAK-01'], ['warehouse_id' => $warehouse->id, 'parent_id' => $zone->id, 'type' => 'rack', 'code' => 'RAK-01', 'name' => 'Rak 01 Demo', 'capacity' => 1000, 'item_type' => 'Fashion', 'is_active' => true]);

        return WarehouseLocation::query()->updateOrCreate(['full_code' => 'GDG-DEMO-ZONA-A-RAK-01-BIN-01'], ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-01', 'name' => 'Bin 01 Demo', 'capacity' => 250, 'item_type' => 'Fashion', 'is_active' => true]);
    }

    /** @param array<string, User> $users */
    private function seedCustomer(array $users): Customer
    {
        $customer = Customer::query()->updateOrCreate(
            ['code' => 'CUS-DEMO-FULL'],
            [
                'type' => 'b2b',
                'business_name' => 'PT Langganan Demo',
                'owner_name' => 'Dewi Langganan',
                'pic_name' => 'Raka PIC',
                'whatsapp_number' => '628122222222',
                'email' => 'customer.demo@gudangtoko.test',
                'business_address' => 'Jl. Pelanggan Demo No. 7',
                'city' => 'Jakarta',
                'price_category' => 'grosir',
                'minimum_order' => 250000,
                'payment_term_days' => 14,
                'credit_limit' => 10000000,
                'receivable_balance' => 0,
                'verification_status' => 'active',
                'account_status' => 'active',
                'is_active' => true,
            ],
        );

        $customer->addresses()->updateOrCreate(['label' => 'Gudang Pelanggan'], ['recipient_name' => 'Raka PIC', 'phone_number' => '628122222222', 'address' => 'Jl. Pelanggan Demo No. 7', 'city' => 'Jakarta', 'is_primary' => true, 'primary_scope' => 'primary']);
        $customer->creditLimit()->updateOrCreate(['customer_id' => $customer->id], ['credit_limit' => 10000000, 'payment_term_days' => 14, 'current_balance' => 0, 'status' => 'active', 'effective_from' => now()->toDateString()]);
        $customer->users()->syncWithoutDetaching([
            $users['langganan_b2b']->id => ['role' => 'langganan_owner', 'is_active' => true],
            $users['akun_pelanggan']->id => ['role' => 'langganan_staff', 'is_active' => true],
        ]);

        return $customer;
    }

    /** @param list<Product> $products */
    private function seedInventory(array $products, WorkLocation $warehouseLocation, WorkLocation $branchLocation, WarehouseLocation $bin, User $actor): void
    {
        $inventory = app(InventoryService::class);

        foreach ($products as $index => $product) {
            $inventory->receive($product, $warehouseLocation, $bin, (string) (150 - ($index * 20)), $actor, ['type' => 'demo_seed', 'no' => 'DEMO-OPENING-WH'], 'Saldo pembuka demo gudang.', "demo-opening-wh-{$product->sku}");
            $inventory->receive($product, $branchLocation, null, (string) (30 - ($index * 5)), $actor, ['type' => 'demo_seed', 'no' => 'DEMO-OPENING-BR'], 'Saldo pembuka demo toko.', "demo-opening-br-{$product->sku}");

            $stock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $warehouseLocation->id)->first();
            StockBatch::query()->updateOrCreate(['product_id' => $product->id, 'batch_no' => 'DEMO-BATCH-'.$product->sku], ['supplier_id' => Supplier::query()->where('code', 'SUP-DEMO-FULL')->value('id'), 'stock_id' => $stock?->id, 'received_at' => now()->subDays(5)->toDateString(), 'expires_at' => now()->addYear()->toDateString(), 'cost_price' => $product->cost_price, 'quantity_on_hand' => 100, 'quantity_reserved' => 0, 'status' => 'active']);
        }
    }

    /** @param list<Product> $products */
    private function seedPricing(array $products, Branch $branch): void
    {
        PriceRule::query()->updateOrCreate(['name' => 'Demo Retail Standard'], ['channel' => 'retail', 'branch_id' => $branch->id, 'margin_method' => 'percent', 'minimum_margin_percent' => 20, 'minimum_margin_amount' => 0, 'overpricing_tolerance_percent' => 50, 'max_discount_percent' => 10, 'approval_threshold_amount' => 0, 'priority' => 10, 'starts_at' => now()->subMonth()->toDateString(), 'is_active' => true]);

        foreach ($products as $product) {
            $minimumPrice = (string) $product->minimum_price;
            $recommendedPrice = bcadd($minimumPrice, '15000.00', 2);
            $maxPrice = bcadd($minimumPrice, '50000.00', 2);

            ProductPrice::query()->updateOrCreate(['product_id' => $product->id, 'branch_id' => $branch->id, 'channel' => 'pos', 'price_ring' => 'retail'], ['customer_category' => null, 'min_price' => $minimumPrice, 'recommended_price' => $recommendedPrice, 'max_price' => $maxPrice, 'minimum_qty' => 1, 'priority' => 10, 'starts_at' => now()->subMonth()->toDateString(), 'status' => 'active', 'notes' => 'Harga demo POS.']);
        }
    }

    /** @param array<string, User> $users */
    private function seedPurchasingAndReceipt(Warehouse $warehouse, Supplier $supplier, Product $product, Unit $unit, WarehouseLocation $bin, array $users): void
    {
        $po = PurchaseOrder::query()->updateOrCreate(['number' => 'PO-DEMO-0001'], ['warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id, 'order_date' => now()->subDays(8)->toDateString(), 'expected_at' => now()->subDays(1)->toDateString(), 'payment_term_days' => 30, 'notes' => 'PO demo lengkap.', 'status' => 'partially_received', 'created_by' => $users['manajemen_gudang']->id, 'submitted_at' => now()->subDays(8), 'approved_at' => now()->subDays(7), 'approved_by' => $users['manajemen_gudang']->id, 'items_subtotal' => 700000, 'header_discount' => 0, 'freight_cost' => 25000, 'additional_cost' => 0, 'grand_total' => 725000]);
        $poItem = $po->items()->updateOrCreate(['product_id' => $product->id], ['unit_id' => $unit->id, 'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $unit->name, 'conversion_factor_snapshot' => 1, 'quantity_ordered' => 20, 'quantity_received' => 10, 'unit_price' => $product->cost_price, 'discount_amount' => 0, 'tax_amount' => 0, 'subtotal' => 700000]);

        $receipt = GoodsReceipt::query()->updateOrCreate(['number' => 'RCV-DEMO-0001'], ['purchase_order_id' => $po->id, 'warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id, 'received_at' => now()->subDays(2)->toDateString(), 'delivery_note_number' => 'SJ-DEMO-0001', 'received_by' => $users['staff_gudang']->id, 'status' => 'posted', 'posted_at' => now()->subDays(2), 'posted_by' => $users['manajemen_gudang']->id, 'actual_freight_cost' => 25000, 'actual_additional_cost' => 0, 'notes' => 'Receipt demo parsial.']);
        $hppAfter = bcadd((string) $product->cost_price, '500.00', 2);
        $receiptItem = GoodsReceiptItem::query()->updateOrCreate(['goods_receipt_id' => $receipt->id, 'product_id' => $product->id], ['purchase_order_item_id' => $poItem->id, 'unit_id' => $unit->id, 'warehouse_location_id' => $bin->id, 'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $unit->name, 'conversion_factor_snapshot' => 1, 'quantity_ordered' => 20, 'previously_received' => 0, 'outstanding_before' => 20, 'quantity_received' => 12, 'quantity_accepted' => 10, 'quantity_rejected' => 1, 'quantity_damaged' => 1, 'unit_price' => $product->cost_price, 'landed_cost_allocated' => 12500, 'batch_no' => 'RCV-BATCH-DEMO-001', 'hpp_before' => $product->cost_price, 'incoming_cost' => $product->cost_price, 'hpp_after' => $hppAfter]);
        ReceiptQcResult::query()->updateOrCreate(['goods_receipt_item_id' => $receiptItem->id, 'qc_status' => 'accepted'], ['quantity' => 10, 'reason' => 'Barang sesuai.']);
        ProductCostHistory::query()->updateOrCreate(['product_id' => $product->id, 'goods_receipt_id' => $receipt->id], ['supplier_id' => $supplier->id, 'goods_receipt_item_id' => $receiptItem->id, 'method' => 'moving_average', 'qty_before' => 150, 'qty_incoming' => 10, 'qty_after' => 160, 'hpp_before' => $product->cost_price, 'incoming_cost' => $product->cost_price, 'landed_cost_allocated' => 12500, 'hpp_after' => $hppAfter, 'effective_at' => now()->subDays(2)]);
        SupplierScore::query()->updateOrCreate(['supplier_id' => $supplier->id, 'goods_receipt_id' => $receipt->id], ['quantity_received' => 12, 'quantity_accepted' => 10, 'quantity_rejected' => 1, 'quantity_damaged' => 1, 'quality_score' => 83, 'delivery_score' => 90, 'price_score' => 85, 'total_score' => 86, 'received_at' => now()->subDays(2)->toDateString()]);
    }

    /** @param array<string, User> $users */
    private function seedRestockAndTransfer(Warehouse $warehouse, Branch $branch, WorkLocation $warehouseLocation, WorkLocation $branchLocation, WarehouseLocation $bin, Product $product, Unit $unit, array $users): void
    {
        $request = RestockRequest::query()->updateOrCreate(['number' => 'RST-DEMO-0001'], ['branch_id' => $branch->id, 'source_warehouse_id' => $warehouse->id, 'requested_by' => $users['toko_internal']->id, 'approved_by' => $users['manajemen_gudang']->id, 'status' => 'approved', 'priority' => 'normal', 'needed_at' => now()->addDays(2)->toDateString(), 'submitted_at' => now()->subDay(), 'approved_at' => now(), 'notes' => 'Restock demo.']);
        $requestItem = RestockRequestItem::query()->updateOrCreate(['restock_request_id' => $request->id, 'product_id' => $product->id], ['quantity_requested' => 12, 'quantity_approved' => 10, 'priority' => 'normal', 'notes' => 'Top up stok toko.']);
        $transfer = StockTransfer::query()->updateOrCreate(['number' => 'TRF-DEMO-0001'], ['restock_request_id' => $request->id, 'source_work_location_id' => $warehouseLocation->id, 'source_warehouse_location_id' => $bin->id, 'destination_work_location_id' => $branchLocation->id, 'destination_warehouse_location_id' => null, 'requested_by' => $users['toko_internal']->id, 'approved_by' => null, 'picker_by' => null, 'shipper_by' => null, 'status' => 'pending_approval', 'transfer_date' => now()->toDateString(), 'submitted_at' => now()->subDay(), 'approved_at' => null, 'packing_started_at' => null, 'notes' => 'Transfer demo menunggu approval agar stok belum berubah sebelum diproses service.']);
        StockTransferItem::query()->updateOrCreate(['stock_transfer_id' => $transfer->id, 'product_id' => $product->id], ['restock_request_item_id' => $requestItem->id, 'unit_id' => $unit->id, 'source_warehouse_location_id' => $bin->id, 'destination_warehouse_location_id' => null, 'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $unit->name, 'conversion_factor_snapshot' => 1, 'quantity_requested' => 12, 'quantity_approved' => 10, 'quantity_reserved' => 0, 'quantity_picked' => 0, 'quantity_short' => 0, 'quantity_shipped' => 0, 'quantity_received' => 0, 'quantity_damaged' => 0, 'quantity_discrepancy' => 0, 'notes' => 'Item transfer demo belum mengunci stok.']);
    }

    /** @param array<string, User> $users */
    private function seedStockOpnameAndLoss(WorkLocation $warehouseLocation, WarehouseLocation $bin, Product $product, array $users): void
    {
        $stock = Stock::query()->where('product_id', $product->id)->where('work_location_id', $warehouseLocation->id)->first();
        $opname = StockOpname::query()->updateOrCreate(['number' => 'OPN-DEMO-0001'], ['work_location_id' => $warehouseLocation->id, 'warehouse_location_id' => $bin->id, 'pic_user_id' => $users['manajemen_gudang']->id, 'created_by' => $users['staff_gudang']->id, 'status' => 'counting', 'method' => 'cycle_count', 'freeze_stock' => false, 'blind_count' => false, 'requires_owner_approval' => false, 'scheduled_at' => now()->toDateString(), 'started_at' => now(), 'threshold_qty' => 5, 'threshold_value' => 500000, 'notes' => 'Opname demo.']);
        if ($stock) {
            StockOpnameItem::query()->updateOrCreate(['stock_opname_id' => $opname->id, 'product_id' => $product->id], ['stock_id' => $stock->id, 'warehouse_location_id' => $bin->id, 'counter_user_id' => $users['staff_gudang']->id, 'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'system_qty_snapshot' => $stock->quantity_on_hand, 'counted_qty' => null, 'difference_qty' => 0, 'unit_cost' => $product->cost_price, 'estimated_value' => 0, 'reason' => 'other', 'note' => 'Belum dihitung.']);
        }
        InventoryLoss::query()->updateOrCreate(['number' => 'LOSS-DEMO-0001'], ['work_location_id' => $warehouseLocation->id, 'warehouse_location_id' => $bin->id, 'product_id' => $product->id, 'reported_by' => $users['staff_gudang']->id, 'loss_type' => 'damaged', 'disposition' => 'move_to_damaged', 'status' => 'pending_approval', 'quantity' => 2, 'unit_cost_snapshot' => $product->cost_price, 'loss_value' => bcmul((string) $product->cost_price, '2', 2), 'reason' => 'Barang penyok saat handling demo.', 'reported_at' => now()]);
    }

    /** @param array<string, User> $users */
    private function seedRetail(Branch $branch, WorkLocation $branchLocation, WarehouseLocation $bin, Product $product, Unit $unit, Customer $customer, array $users): void
    {
        $shift = CashShift::query()->updateOrCreate(['number' => 'SHIFT-DEMO-0001'], ['branch_id' => $branch->id, 'work_location_id' => $branchLocation->id, 'cashier_user_id' => $users['kasir_kepala_toko']->id, 'opened_by' => $users['kasir_kepala_toko']->id, 'status' => 'open', 'opening_cash_amount' => 500000, 'expected_cash_amount' => 500000, 'terminal_code' => 'POS-DEMO-01', 'cash_sales_amount' => 0, 'non_cash_sales_amount' => 0, 'refund_amount' => 0, 'expense_amount' => 0, 'receivable_amount' => 0, 'difference_amount' => 0, 'discrepancy_threshold_amount' => 50000, 'opened_at' => now(), 'notes' => 'Shift demo terbuka.']);
        $sale = PosSale::query()->updateOrCreate(['number' => 'POS-DEMO-0001'], ['branch_id' => $branch->id, 'work_location_id' => $branchLocation->id, 'cash_shift_id' => $shift->id, 'cashier_user_id' => $users['kasir_kepala_toko']->id, 'customer_id' => $customer->id, 'status' => 'completed', 'subtotal_amount' => 59000, 'discount_amount' => 0, 'tax_amount' => 0, 'grand_total_amount' => 59000, 'paid_amount' => 60000, 'change_amount' => 1000, 'total_margin_amount' => 24000, 'idempotency_key' => 'demo-pos-sale-0001', 'completed_at' => now(), 'notes' => 'Penjualan demo.']);
        PosSaleItem::query()->updateOrCreate(['pos_sale_id' => $sale->id, 'product_id' => $product->id], ['unit_id' => $unit->id, 'warehouse_location_id' => $bin->id, 'sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $unit->name, 'conversion_factor_snapshot' => 1, 'quantity' => 1, 'base_quantity' => 1, 'hpp_snapshot' => $product->cost_price, 'minimum_price_snapshot' => $product->minimum_price, 'selected_price' => 59000, 'discount_percent' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => 59000, 'margin_amount' => 24000, 'price_source' => 'demo_seed', 'price_snapshot' => ['source' => 'demo_seed'], 'returned_quantity' => 0]);
        SalePayment::query()->updateOrCreate(['pos_sale_id' => $sale->id, 'method' => 'cash'], ['amount' => 59000, 'reference_no' => 'CASH-DEMO', 'notes' => 'Tunai demo.']);
    }

    /** @param array<string, User> $users */
    private function seedB2bInvoiceShipmentReceivable(WorkLocation $warehouseLocation, Product $product, Unit $unit, Customer $customer, array $users): void
    {
        $address = $customer->addresses()->first();
        $order = B2bOrder::query()->updateOrCreate(['number' => 'B2B-DEMO-0001'], ['customer_id' => $customer->id, 'requested_by' => $users['langganan_b2b']->id, 'approved_by' => $users['manajemen_gudang']->id, 'customer_address_id' => $address?->id, 'status' => 'invoice_ready', 'requested_delivery_date' => now()->addDays(2)->toDateString(), 'delivery_method' => 'courier', 'courier_name' => 'Kurir Demo', 'payment_preference' => 'credit', 'terms_accepted' => true, 'subtotal_amount' => 178000, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_cost_amount' => 15000, 'grand_total_amount' => 193000, 'credit_limit_snapshot' => 10000000, 'receivable_balance_snapshot' => 0, 'notes' => 'Order B2B demo.', 'submitted_at' => now()->subDay(), 'approved_at' => now()]);
        $orderItem = B2bOrderItem::query()->updateOrCreate(['b2b_order_id' => $order->id, 'product_id' => $product->id], ['unit_id' => $unit->id, 'sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name, 'unit_name_snapshot' => $unit->name, 'conversion_factor_snapshot' => 1, 'quantity' => 2, 'approved_quantity' => 2, 'base_quantity' => 2, 'reserved_quantity' => 0, 'issued_quantity' => 0, 'shortage_quantity' => 0, 'fulfillment_status' => 'approved', 'minimum_price_snapshot' => $product->minimum_price, 'selected_price' => 89000, 'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => 178000, 'price_source' => 'demo_seed', 'available_stock_snapshot' => 100, 'price_snapshot' => ['source' => 'demo_seed']]);
        $invoice = Invoice::query()->updateOrCreate(['number' => 'INV-DEMO-0001'], ['source_type' => 'b2b_order', 'b2b_order_id' => $order->id, 'customer_id' => $customer->id, 'status' => 'issued', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'subtotal_amount' => 178000, 'discount_amount' => 0, 'shipping_amount' => 15000, 'tax_amount' => 0, 'total_amount' => 193000, 'paid_amount' => 0, 'outstanding_amount' => 193000, 'issued_at' => now(), 'created_by' => $users['manajemen_gudang']->id, 'issued_by' => $users['manajemen_gudang']->id, 'notes' => 'Invoice demo.']);
        InvoiceItem::query()->updateOrCreate(['invoice_id' => $invoice->id, 'product_id' => $product->id], ['b2b_order_item_id' => $orderItem->id, 'description' => $product->name, 'unit_name_snapshot' => $unit->name, 'quantity' => 2, 'unit_price' => 89000, 'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => 178000]);
        $receivable = Receivable::query()->updateOrCreate(['number' => 'AR-DEMO-0001'], ['customer_id' => $customer->id, 'work_location_id' => $warehouseLocation->id, 'invoice_id' => $invoice->id, 'source_type' => 'invoice', 'source_id' => $invoice->id, 'source_no' => $invoice->number, 'channel' => 'warehouse', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'principal_amount' => 193000, 'adjustment_amount' => 0, 'paid_amount' => 0, 'outstanding_amount' => 193000, 'aging_bucket' => 'not_due', 'status' => 'open']);
        ReceivableEntry::query()->updateOrCreate(['receivable_id' => $receivable->id, 'entry_type' => 'invoice'], ['customer_id' => $customer->id, 'amount' => 193000, 'balance_before' => 0, 'balance_after' => 193000, 'source_type' => 'invoice', 'source_id' => $invoice->id, 'source_no' => $invoice->number, 'actor_user_id' => $users['manajemen_gudang']->id, 'notes' => 'Piutang demo.', 'occurred_at' => now()]);
        Payment::query()->updateOrCreate(['number' => 'PAY-DEMO-0001'], ['customer_id' => $customer->id, 'method' => 'bank_transfer', 'status' => 'pending_verification', 'amount' => 100000, 'payment_date' => now()->toDateString(), 'bank_name' => 'BCA', 'reference_no' => 'TRX-DEMO-0001', 'payer_name' => $customer->business_name, 'received_by' => $users['staff_gudang']->id, 'notes' => 'Pembayaran demo menunggu verifikasi.']);
        $shipment = Shipment::query()->updateOrCreate(['number' => 'SHP-DEMO-0001'], ['b2b_order_id' => $order->id, 'customer_id' => $customer->id, 'origin_work_location_id' => $warehouseLocation->id, 'destination_address_id' => $address?->id, 'status' => 'packing', 'delivery_method' => 'courier', 'courier_name' => 'Kurir Demo', 'scheduled_date' => now()->addDay()->toDateString(), 'shipping_cost_amount' => 15000, 'created_by' => $users['staff_gudang']->id]);
        ShipmentItem::query()->updateOrCreate(['shipment_id' => $shipment->id, 'product_id' => $product->id], ['b2b_order_item_id' => $orderItem->id, 'quantity_planned' => 2, 'quantity_shipped' => 0, 'quantity_delivered' => 0, 'quantity_failed' => 0, 'status' => 'packing']);
        B2bComplaint::query()->updateOrCreate(['number' => 'CMP-DEMO-0001'], ['customer_id' => $customer->id, 'b2b_order_id' => $order->id, 'shipment_id' => $shipment->id, 'b2b_order_item_id' => $orderItem->id, 'type' => 'informasi', 'requested_solution' => 'follow_up', 'quantity' => 1, 'status' => 'submitted', 'message' => 'Komplain demo untuk latihan follow up.', 'created_by' => $users['akun_pelanggan']->id]);
    }

    /** @param array<string, User> $users */
    private function seedControlReports(array $users): void
    {
        $approval = app(ApprovalWorkflowService::class)->create($users['staff_gudang'], 'demo_sensitive_action', 'demo', $users['staff_gudang'], '250000.00', 'Approval demo untuk latihan owner.', ['status' => 'draft'], ['status' => 'approved']);
        AnomalyAlert::query()->updateOrCreate(['rule_key' => 'demo-large-discount', 'subject_type' => ApprovalRequest::class, 'subject_id' => $approval->id], ['title' => 'Diskon demo perlu review', 'description' => 'Contoh alert anomali untuk dashboard audit.', 'severity' => 'medium', 'risk_value' => 250000, 'evidence' => ['source' => 'demo_seed'], 'status' => 'open', 'detected_at' => now()]);
        AuditLog::query()->updateOrCreate(['event' => 'demo.seed.generated', 'module' => 'demo'], ['actor_user_id' => $users['super_admin']->id, 'new_values' => ['message' => 'Seeder demo lengkap dijalankan.'], 'severity' => 'info', 'occurred_at' => now()]);
        ReportExport::query()->updateOrCreate(['report_type' => 'daily', 'format' => 'xlsx', 'requested_by' => $users['owner']->id], ['status' => 'completed', 'filters' => ['period' => 'today'], 'progress' => 100, 'row_count' => 10, 'disk' => 'local', 'file_path' => 'private/reports/demo-daily.xlsx', 'started_at' => now()->subMinutes(5), 'finished_at' => now(), 'expires_at' => now()->addDays(7), 'correlation_id' => 'demo-report']);
        DailyReport::query()->updateOrCreate(['idempotency_key' => 'demo-daily-report'], ['report_date' => now()->toDateString(), 'period_start' => now()->toDateString(), 'period_end' => now()->toDateString(), 'status' => 'generated', 'filters' => ['scope' => 'demo'], 'summary' => ['revenue' => 59000, 'gross_margin' => 24000], 'rows' => [], 'definitions' => [], 'generated_at' => now(), 'generated_by' => $users['owner']->id]);
    }
}
