<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\NotificationChannel;
use App\Models\NotificationRecipient;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SystemSetting;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use App\Services\Inventory\InventoryService;
use App\Services\Organization\DocumentNumberService;
use App\Services\Organization\WorkLocationSyncService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocalDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local'), 403, 'Seeder lokal hanya boleh dijalankan pada environment local.');

        $this->call(RolePermissionSeeder::class);
        $sync = app(WorkLocationSyncService::class);

        $accounts = [
            'super_admin' => ['Admin Lokal', 'admin', 'admin@gudangtoko.test'],
            'owner_viewer' => ['Owner Viewer Lokal', 'owner', 'owner@gudangtoko.test'],
            'owner_approver' => ['Owner Approver Lokal', 'approver', 'approver@gudangtoko.test'],
            'kepala_gudang' => ['Kepala Gudang Lokal', 'gudang', 'gudang@gudangtoko.test'],
            'purchasing' => ['Purchasing Lokal', 'purchasing', 'purchasing@gudangtoko.test'],
            'kepala_toko' => ['Kepala Toko Lokal', 'retail', 'retail@gudangtoko.test'],
            'kasir' => ['Kasir Lokal', 'kasir', 'kasir@gudangtoko.test'],
            'admin_config' => ['Admin Config Lokal', 'adminconfig', 'adminconfig@gudangtoko.test'],
            'admin_user' => ['Admin User Lokal', 'adminuser', 'adminuser@gudangtoko.test'],
            'langganan_owner' => ['Langganan Owner Lokal', 'b2bowner', 'b2bowner@gudangtoko.test'],
            'langganan_staff' => ['Langganan Staff Lokal', 'b2bstaff', 'b2bstaff@gudangtoko.test'],
        ];

        foreach ($accounts as $roleName => [$name, $username, $email]) {
            $role = Role::findOrCreate($roleName);

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$role]);
        }

        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-UTAMA'],
            [
                'name' => 'Gudang Utama',
                'address' => 'Jl. Gudang Utama No. 1',
                'city' => 'Jakarta',
                'phone_number' => '021-5550001',
                'manager_user_id' => User::query()->where('email', 'gudang@gudangtoko.test')->value('id'),
                'capacity' => 10000,
                'service_area' => 'Jabodetabek',
                'is_active' => true,
            ],
        );
        $warehouseLocation = $sync->syncWarehouse($warehouse);

        $branch = Branch::query()->updateOrCreate(
            ['code' => 'TKO-UTAMA'],
            [
                'primary_warehouse_id' => $warehouse->id,
                'name' => 'Toko Utama',
                'address' => 'Jl. Toko Utama No. 1',
                'phone_number' => '021-5550002',
                'manager_user_id' => User::query()->where('email', 'retail@gudangtoko.test')->value('id'),
                'sales_target' => 50000000,
                'price_configuration' => 'standard',
                'closing_configuration' => 'daily',
                'is_closing_required' => true,
                'is_active' => true,
            ],
        );
        $branchLocation = $sync->syncBranch($branch);

        User::query()->where('email', 'gudang@gudangtoko.test')->first()?->workLocations()->syncWithoutDetaching([
            $warehouseLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        User::query()->where('email', 'purchasing@gudangtoko.test')->first()?->workLocations()->syncWithoutDetaching([
            $warehouseLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        User::query()->where('email', 'retail@gudangtoko.test')->first()?->workLocations()->syncWithoutDetaching([
            $branchLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        User::query()->where('email', 'kasir@gudangtoko.test')->first()?->workLocations()->syncWithoutDetaching([
            $branchLocation->id => ['is_default' => true, 'is_active' => true],
        ]);

        $this->seedLocalAttendance($warehouseLocation, $branchLocation);

        foreach ($this->defaultSettings() as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        }

        foreach (DocumentNumberService::DEFAULT_PREFIXES as $type => $prefix) {
            DocumentSequence::query()->firstOrCreate(
                ['document_type' => $type, 'location_type' => null, 'location_id' => null, 'scope_key' => 'global', 'year' => (int) now()->format('Y')],
                ['prefix' => $prefix, 'next_number' => 1, 'padding' => 5, 'reset_yearly' => true, 'format' => '{prefix}/{location}/{year}/{sequence}'],
            );
        }

        $units = [
            ['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'precision' => 0],
            ['code' => 'PACK', 'name' => 'Pack', 'symbol' => 'pack', 'precision' => 0],
            ['code' => 'DUS', 'name' => 'Dus', 'symbol' => 'dus', 'precision' => 0],
            ['code' => 'LUSIN', 'name' => 'Lusin', 'symbol' => 'lsn', 'precision' => 0],
            ['code' => 'KODI', 'name' => 'Kodi', 'symbol' => 'kodi', 'precision' => 0],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], [...$unit, 'is_active' => true]);
        }

        $category = ProductCategory::query()->updateOrCreate(
            ['code' => 'UMUM'],
            ['name' => 'Produk Umum', 'sort_order' => 1, 'icon' => 'ki-outline ki-parcel', 'is_active' => true],
        );
        $brand = ProductBrand::query()->updateOrCreate(
            ['code' => 'NO-BRAND'],
            ['name' => 'Tanpa Merek', 'description' => 'Merek default untuk produk demo lokal.', 'is_active' => true],
        );
        $pcs = Unit::query()->where('code', 'PCS')->firstOrFail();
        $pack = Unit::query()->where('code', 'PACK')->firstOrFail();

        $product = Product::query()->updateOrCreate(
            ['sku' => 'PRD-DEMO-001'],
            [
                'name' => 'Produk Demo Lokal',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'base_unit_id' => $pcs->id,
                'status' => 'active',
                'minimum_order' => 1,
                'minimum_stock' => 10,
                'safety_stock' => 5,
                'default_warehouse_id' => $warehouse->id,
                'cost_price' => 10000,
                'minimum_price' => 12000,
            ],
        );

        ProductUnit::query()->updateOrCreate(['product_id' => $product->id, 'unit_id' => $pcs->id], ['name' => 'Satuan dasar', 'conversion_factor' => 1, 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);
        ProductUnit::query()->updateOrCreate(['product_id' => $product->id, 'unit_id' => $pack->id], ['name' => 'Pack isi 12', 'conversion_factor' => 12, 'is_base' => false, 'is_sellable' => true, 'is_active' => true]);
        ProductBarcode::query()->updateOrCreate(['code' => '899000000001'], ['product_id' => $product->id, 'product_unit_id' => ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $pcs->id)->value('id'), 'type' => 'barcode', 'is_primary' => true, 'is_active' => true]);

        $supplier = Supplier::query()->updateOrCreate(
            ['code' => 'SUP-DEMO'],
            [
                'name' => 'Supplier Demo Lokal',
                'contact_name' => 'Budi Supplier',
                'whatsapp_number' => '081234567890',
                'email' => 'supplier@gudangtoko.test',
                'address' => 'Jl. Supplier No. 1',
                'city' => 'Jakarta',
                'tax_number' => '09.123.456.7-001.000',
                'bank_name' => 'BCA',
                'bank_account_name' => 'Supplier Demo Lokal',
                'bank_account_number' => '1234567890',
                'payment_term_days' => 30,
                'last_price' => 10000,
                'performance_score' => 85,
                'is_active' => true,
            ],
        );
        SupplierProduct::query()->updateOrCreate(['supplier_id' => $supplier->id, 'product_id' => $product->id], ['last_price' => 10000, 'last_supplied_at' => now()]);

        $zone = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-UTAMA-ZONA-A'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => null, 'type' => 'zone', 'code' => 'ZONA-A', 'name' => 'Zona A', 'capacity' => 5000, 'item_type' => 'Produk umum', 'is_active' => true],
        );
        $rack = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-UTAMA-ZONA-A-RAK-A1'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $zone->id, 'type' => 'rack', 'code' => 'RAK-A1', 'name' => 'Rak A1', 'capacity' => 1000, 'item_type' => 'Produk umum', 'is_active' => true],
        );
        $bin = WarehouseLocation::query()->updateOrCreate(
            ['full_code' => 'GDG-UTAMA-ZONA-A-RAK-A1-BIN-01'],
            ['warehouse_id' => $warehouse->id, 'parent_id' => $rack->id, 'type' => 'bin', 'code' => 'BIN-01', 'name' => 'Bin 01', 'capacity' => 200, 'item_type' => 'Produk umum', 'is_active' => true],
        );

        app(InventoryService::class)->receive(
            product: $product,
            workLocation: $warehouseLocation,
            warehouseLocation: $bin,
            quantity: '120',
            actor: User::query()->where('email', 'gudang@gudangtoko.test')->first(),
            reference: ['type' => 'local_seed', 'no' => 'SEED-STOCK-OPENING'],
            reason: 'Saldo pembuka data uji lokal.',
            idempotencyKey: 'local-seed-opening-stock-prd-demo-001',
        );

        $stock = Stock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_location_id', $bin->id)
            ->first();

        StockBatch::query()->updateOrCreate(
            ['product_id' => $product->id, 'batch_no' => 'BATCH-DEMO-001'],
            [
                'supplier_id' => $supplier->id,
                'stock_id' => $stock?->id,
                'received_at' => now()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'cost_price' => 10000,
                'quantity_on_hand' => 120,
                'quantity_reserved' => 0,
                'status' => 'active',
            ],
        );

        if (! PurchaseOrder::query()->where('notes', 'PO demo lokal P10')->exists()) {
            app(PurchaseOrderService::class)->create([
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'order_date' => now()->toDateString(),
                'expected_at' => now()->addDays(7)->toDateString(),
                'payment_term_days' => 30,
                'notes' => 'PO demo lokal P10',
                'header_discount' => 0,
                'freight_cost' => 25000,
                'additional_cost' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'unit_id' => $pcs->id,
                        'quantity_ordered' => 24,
                        'unit_price' => 10000,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                    ],
                ],
            ], User::query()->where('email', 'purchasing@gudangtoko.test')->firstOrFail());
        }

        $customer = Customer::query()->updateOrCreate(
            ['code' => 'CUS-B2B-DEMO'],
            [
                'type' => 'b2b',
                'business_name' => 'Pelanggan B2B Demo',
                'owner_name' => 'Sari Owner',
                'pic_name' => 'Sari PIC',
                'whatsapp_number' => '081234567891',
                'email' => 'customer@gudangtoko.test',
                'business_address' => 'Jl. Customer No. 1',
                'city' => 'Jakarta',
                'price_category' => 'grosir',
                'minimum_order' => 500000,
                'payment_term_days' => 14,
                'credit_limit' => 5000000,
                'receivable_balance' => 0,
                'verification_status' => 'active',
                'account_status' => 'active',
                'is_active' => true,
            ],
        );
        $customer->addresses()->updateOrCreate(['label' => 'Utama'], ['recipient_name' => 'Sari PIC', 'phone_number' => '081234567891', 'address' => 'Jl. Customer No. 1', 'city' => 'Jakarta', 'is_primary' => true, 'primary_scope' => 'primary']);
        $customer->creditLimit()->updateOrCreate(['customer_id' => $customer->id], ['credit_limit' => 5000000, 'payment_term_days' => 14, 'current_balance' => 0, 'effective_from' => now()->toDateString()]);
        foreach (['b2bowner@gudangtoko.test' => 'langganan_owner', 'b2bstaff@gudangtoko.test' => 'langganan_staff'] as $email => $role) {
            $b2bUser = User::query()->where('email', $email)->first();
            if ($b2bUser) {
                $customer->users()->syncWithoutDetaching([$b2bUser->id => ['role' => $role, 'is_active' => true]]);
            }
        }

        $this->seedLocalNotifications($warehouseLocation, $branchLocation);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedLocalNotifications(WorkLocation $warehouseLocation, WorkLocation $branchLocation): void
    {
        $owner = User::query()->where('email', 'approver@gudangtoko.test')->first();

        $waTemplate = NotificationTemplate::query()->updateOrCreate(
            ['key' => 'daily_report', 'channel_type' => 'whatsapp', 'version' => 1],
            [
                'name' => 'Laporan Harian Owner WA',
                'subject' => 'Laporan Harian {{ report_date }}',
                'body' => "Ringkasan GudangToko {{ report_date }}\nOmzet: {{ revenue }}\nMargin: {{ gross_margin }} ({{ margin_percent }}%)\nStok kritis: {{ critical_stock_count }}\nPiutang overdue: {{ overdue_receivable }}\nDetail aman: {{ secure_link }}",
                'fallback_body' => 'Laporan harian tersedia. Silakan login ke GudangToko.',
                'allowed_variables' => config('notifications.template_variables.daily_report'),
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        NotificationTemplate::query()->updateOrCreate(
            ['key' => 'daily_report', 'channel_type' => 'telegram', 'version' => 1],
            [
                'name' => 'Laporan Harian Owner Telegram',
                'subject' => 'Laporan Harian {{ report_date }}',
                'body' => "<b>GudangToko {{ report_date }}</b>\nOmzet: {{ revenue }}\nMargin: {{ gross_margin }}\nStok kritis: {{ critical_stock_count }}\nDetail: {{ secure_link }}",
                'fallback_body' => 'Laporan harian tersedia.',
                'allowed_variables' => config('notifications.template_variables.daily_report'),
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        NotificationChannel::query()->updateOrCreate(
            ['name' => 'WA Dry Run Lokal', 'channel_type' => 'whatsapp'],
            [
                'endpoint' => 'https://example.invalid/wa/send',
                'auth_type' => 'bearer',
                'credentials' => ['token' => 'local-only-not-production'],
                'sender' => 'gudangtoko-local',
                'default_destination' => '6281234567890',
                'timeout_seconds' => 10,
                'retry_attempts' => 3,
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        NotificationChannel::query()->updateOrCreate(
            ['name' => 'Telegram Dry Run Lokal', 'channel_type' => 'telegram'],
            [
                'credentials' => ['bot_token' => 'local-only-not-production'],
                'default_destination' => '123456789',
                'timeout_seconds' => 10,
                'retry_attempts' => 3,
                'is_active' => true,
                'created_by' => $owner?->id,
            ],
        );

        NotificationRecipient::query()->updateOrCreate(
            ['destination' => '6281234567890', 'channel_type' => 'whatsapp', 'report_type' => 'daily_report'],
            [
                'name' => 'Owner Approver WA Lokal',
                'recipient_type' => 'user',
                'user_id' => $owner?->id,
                'role_name' => 'owner_approver',
                'work_location_id' => null,
                'is_verified' => true,
                'is_active' => true,
            ],
        );

        NotificationSchedule::query()->updateOrCreate(
            ['schedule_key' => 'daily-owner-local'],
            [
                'name' => 'Laporan Harian Owner Lokal',
                'frequency' => 'daily',
                'run_time' => config('notifications.daily_report_time', '08:00'),
                'timezone' => 'Asia/Jakarta',
                'report_type' => 'daily_report',
                'report_period' => 'yesterday',
                'template_id' => $waTemplate->id,
                'channel_types' => ['whatsapp'],
                'recipient_scope' => ['role_name' => 'owner_approver'],
                'work_location_id' => null,
                'is_active' => true,
            ],
        );

        foreach ([
            ['critical_stock', 'Stok Kritis Lokal', 'critical_stock', 'high', 10],
            ['overdue_receivable', 'Piutang Overdue Lokal', 'receivable_due', 'high', 1],
            ['closing_difference', 'Selisih Closing Lokal', 'closing_difference', 'medium', 50000],
        ] as [$key, $name, $type, $severity, $threshold]) {
            AlertRule::query()->updateOrCreate(
                ['rule_key' => $key],
                [
                    'name' => $name,
                    'alert_type' => $type,
                    'severity' => $severity,
                    'threshold_value' => $threshold,
                    'cooldown_minutes' => 60,
                    'channel_types' => ['whatsapp'],
                    'recipient_scope' => ['warehouse_location_id' => $warehouseLocation->id, 'branch_location_id' => $branchLocation->id],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedLocalAttendance(WorkLocation $warehouseLocation, WorkLocation $branchLocation): void
    {
        $employees = [
            ['email' => 'gudang@gudangtoko.test', 'employee_no' => 'EMP-GDG-001', 'position' => 'Kepala Gudang', 'location_id' => $warehouseLocation->id],
            ['email' => 'retail@gudangtoko.test', 'employee_no' => 'EMP-TKO-001', 'position' => 'Kepala Toko', 'location_id' => $branchLocation->id],
            ['email' => 'kasir@gudangtoko.test', 'employee_no' => 'EMP-TKO-002', 'position' => 'Kasir', 'location_id' => $branchLocation->id],
        ];

        foreach ($employees as $row) {
            $user = User::query()->where('email', $row['email'])->first();
            if (! $user instanceof User) {
                continue;
            }
            $employee = Employee::query()
                ->where('employee_no', $row['employee_no'])
                ->orWhere('user_id', $user->id)
                ->first() ?? new Employee;

            $employee->fill([
                'employee_no' => $row['employee_no'],
                'user_id' => $user->id,
                'work_location_id' => $row['location_id'],
                'name' => $user->name,
                'position' => $row['position'],
                'whatsapp_number' => $user->phone_number,
                'joined_at' => now()->startOfMonth()->toDateString(),
                'status' => 'active',
                'is_active' => true,
            ])->save();
        }

        $shift = WorkShift::query()->updateOrCreate(
            ['code' => 'SHIFT-PAGI-LOCAL'],
            [
                'work_location_id' => $branchLocation->id,
                'name' => 'Shift Pagi Lokal',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'is_cross_midnight' => false,
                'tolerance_late_minutes' => 10,
                'tolerance_early_leave_minutes' => 10,
                'break_minutes' => 60,
                'work_days' => [1, 2, 3, 4, 5, 6],
                'effective_from' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ],
        );

        Employee::query()->where('work_location_id', $branchLocation->id)->where('is_active', true)->each(function (Employee $employee) use ($branchLocation, $shift): void {
            $scheduledDate = now()->startOfDay();
            $start = now()->setTime(8, 0);
            $end = now()->setTime(16, 0);
            EmployeeSchedule::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'scheduled_date' => $scheduledDate],
                [
                    'work_shift_id' => $shift->id,
                    'work_location_id' => $branchLocation->id,
                    'scheduled_start_at' => $start,
                    'scheduled_end_at' => $end,
                    'status' => 'scheduled',
                    'notes' => 'Jadwal demo lokal hari ini.',
                ],
            );
        });
    }

    /** @return array<string, mixed> */
    private function defaultSettings(): array
    {
        return [
            'company_name' => 'GudangToko',
            'company_address' => 'Jl. Operasional No. 1',
            'company_phone' => '021-5550000',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'upload_limit_mb' => 2,
            'default_minimum_margin_percent' => '10',
            'overpricing_tolerance_percent' => '20',
            'invoice_template' => 'default',
            'receipt_template' => 'default',
        ];
    }
}
