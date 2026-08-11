<?php

namespace Tests\Feature\System;

use App\Exports\ArrayReportExport;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationsDeploymentTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $adminConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole(Role::findOrCreate('super_admin'));

        $this->adminConfig = User::factory()->create(['is_active' => true]);
        $this->adminConfig->assignRole(Role::findOrCreate('admin_config'));
    }

    #[Test]
    public function super_admin_can_open_all_p28_operations_pages(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.system.backups.index'))->assertOk()->assertSee('Backup Database dan File');
        $this->actingAs($this->superAdmin)->get(route('admin.system.logs.index'))->assertOk()->assertSee('Log Aplikasi dan Queue');
        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.imports.index'))
            ->assertOk()
            ->assertSee('Import Data Awal')
            ->assertSee('Jumlah Stok Awal')
            ->assertDontSee('base_quantity');
        $this->actingAs($this->superAdmin)->get(route('admin.system.maintenance.index'))->assertOk()->assertSee('Maintenance dan Go-Live');
    }

    #[Test]
    public function non_super_admin_cannot_open_operations_pages(): void
    {
        $this->actingAs($this->adminConfig)->get(route('admin.system.backups.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.logs.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.imports.index'))->assertForbidden();
        $this->actingAs($this->adminConfig)->get(route('admin.system.maintenance.index'))->assertForbidden();
    }

    #[Test]
    public function backup_page_lists_encrypted_backup_and_requires_signed_download(): void
    {
        Storage::fake('local');
        config(['security.backup.disk' => 'local', 'security.backup.path' => 'private/backups']);
        Storage::disk('local')->put('private/backups/test.sql.enc', 'encrypted-payload');

        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.backups.index'))
            ->assertOk()
            ->assertSee('test.sql.enc')
            ->assertSee(hash('sha256', 'encrypted-payload'));

        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.backups.download', ['file' => rtrim(strtr(base64_encode('private/backups/test.sql.enc'), '+/', '-_'), '=')]))
            ->assertForbidden();

        $signedUrl = URL::temporarySignedRoute('admin.system.backups.download', now()->addMinutes(5), [
            'file' => rtrim(strtr(base64_encode('private/backups/test.sql.enc'), '+/', '-_'), '='),
        ]);

        $this->actingAs($this->superAdmin)->get($signedUrl)->assertOk();
    }

    #[Test]
    public function initial_import_template_and_dry_run_preview_work(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.system.imports.templates.download', 'suppliers'))
            ->assertOk()
            ->assertDownload('template-suppliers.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $file = UploadedFile::fake()->createWithContent('suppliers.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'Kode Supplier' => 'SUP-001',
                    'Nama Supplier' => 'Supplier Test',
                    'Nomor Telepon' => '0812',
                    'Email' => 'supplier@example.test',
                    'Termin Pembayaran (Hari)' => 30,
                ],
            ], ['Kode Supplier', 'Nama Supplier', 'Nomor Telepon', 'Email', 'Termin Pembayaran (Hari)']),
            ExcelFormat::XLSX,
        ));

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.preview'), [
                'type' => 'suppliers',
                'file' => $file,
                'dry_run' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('initial_import_preview.header_labels', [
                'code' => 'Kode Supplier',
                'name' => 'Nama Supplier',
                'phone_number' => 'Nomor Telepon',
                'email' => 'Email',
                'payment_term_days' => 'Termin Pembayaran (Hari)',
            ]);
    }

    #[Test]
    public function initial_import_rejects_csv_files(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'suppliers.csv',
            "code,name,phone_number,email,payment_term_days\nSUP-001,Supplier Test,0812,supplier@example.test,30\n",
        );

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.preview'), [
                'type' => 'suppliers',
                'file' => $file,
                'dry_run' => '1',
            ])
            ->assertSessionHasErrors('file')
            ->assertSessionMissing('initial_import_preview');
    }

    #[Test]
    public function super_admin_can_commit_valid_opening_stock_preview_directly(): void
    {
        $workLocation = WorkLocation::factory()->create([
            'type' => 'warehouse',
            'code' => 'GDG-01',
        ]);
        $warehouse = Warehouse::factory()->create([
            'work_location_id' => $workLocation->id,
            'code' => 'GDG-01',
        ]);
        WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'RAK1',
            'full_code' => 'GU-ZONA1-RAK1',
        ]);
        $product = Product::factory()->create([
            'sku' => 'PRD-TEST-001',
            'cost_price' => 0,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'opening-stocks.xlsx',
            Excel::raw(
                new ArrayReportExport([
                    [
                        'SKU Produk' => 'PRD-TEST-001',
                        'Kode Gudang/Cabang' => 'GDG-01',
                        'Kode Lokasi Gudang' => 'GU-ZONA1-RAK1',
                        'Jumlah Stok Awal' => 10,
                        'HPP' => 5000,
                        'Alasan' => 'Saldo awal persediaan',
                    ],
                ], ['SKU Produk', 'Kode Gudang/Cabang', 'Kode Lokasi Gudang', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
                ExcelFormat::XLSX,
            ),
        );

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.preview'), [
                'type' => 'opening_stocks',
                'file' => $file,
                'dry_run' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('initial_import_preview');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.commit'), [
                'type' => 'opening_stocks',
                'confirmation' => 'COMMIT STOK AWAL',
            ])
            ->assertRedirect()
            ->assertSessionHas('notification.type', 'success');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'cost_price' => 5000]);
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'work_location_id' => $workLocation->id,
            'quantity_on_hand' => 10,
            'cost_value' => 50000,
        ]);
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'mutation_type' => 'adjust',
            'quantity_on_hand_after' => 10,
            'reason' => 'Saldo awal persediaan',
        ]);
        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $product->id,
            'goods_receipt_id' => null,
            'goods_receipt_item_id' => null,
            'method' => 'opening_stock',
            'source_type' => 'opening_stock',
            'changed_by' => $this->superAdmin->id,
            'hpp_before' => 0,
            'hpp_after' => 5000,
        ]);
        $this->assertSame(1, Stock::query()->count());
        $this->assertSame(1, ProductCostHistory::query()->count());

        $updatedFile = UploadedFile::fake()->createWithContent(
            'opening-stocks-updated-hpp.xlsx',
            Excel::raw(
                new ArrayReportExport([
                    [
                        'SKU Produk' => 'PRD-TEST-001',
                        'Kode Gudang/Cabang' => 'GDG-01',
                        'Kode Lokasi Gudang' => 'GU-ZONA1-RAK1',
                        'Jumlah Stok Awal' => 10,
                        'HPP' => 6000,
                        'Alasan' => 'Koreksi HPP stok awal',
                    ],
                ], ['SKU Produk', 'Kode Gudang/Cabang', 'Kode Lokasi Gudang', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
                ExcelFormat::XLSX,
            ),
        );

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.preview'), [
                'type' => 'opening_stocks',
                'file' => $updatedFile,
                'dry_run' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.imports.commit'), [
                'type' => 'opening_stocks',
                'confirmation' => 'COMMIT STOK AWAL',
            ])
            ->assertRedirect()
            ->assertSessionHas('notification.message', fn (string $message): bool => str_contains($message, '1 histori HPP dicatat'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'cost_price' => 6000]);
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'cost_value' => 60000,
        ]);
        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $product->id,
            'method' => 'opening_stock',
            'reason' => 'Koreksi HPP stok awal',
            'qty_incoming' => 0,
            'hpp_before' => 5000,
            'hpp_after' => 6000,
        ]);
        $this->assertSame(2, ProductCostHistory::query()->count());
        $this->assertDatabaseCount('stock_mutations', 1);
    }

    #[Test]
    public function maintenance_action_requires_explicit_confirmation(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.maintenance.run'), [
                'action' => 'queue_restart',
                'confirmation' => 'salah',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.maintenance.run'), [
                'action' => 'queue_restart',
                'confirmation' => 'SAYA MENGERTI',
                'message' => 'Restart worker setelah deploy.',
            ])
            ->assertRedirect();
    }
}
