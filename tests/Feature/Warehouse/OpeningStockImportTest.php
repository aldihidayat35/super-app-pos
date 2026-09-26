<?php

namespace Tests\Feature\Warehouse;

use App\Exports\ArrayReportExport;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpeningStockImportTest extends TestCase
{
    use RefreshDatabase;

    private User $head;

    private User $staff;

    private WorkLocation $warehouseLocation;

    private WarehouseLocation $warehouseBin;

    private WorkLocation $branchLocation;

    private WarehouseLocation $branchBin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->head = $this->createUser('kepala_gudang');
        $this->staff = $this->createUser('staff_gudang');

        // Gudang
        $this->warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-TEST', 'name' => 'Gudang Pusat Test']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->warehouseLocation->id, 'code' => 'WH-TEST']);
        $this->warehouseBin = WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'RAK-01',
            'full_code' => 'WH-TEST-RAK-01',
            'type' => 'bin',
            'is_active' => true,
        ]);

        // Toko / Cabang
        $this->branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-TEST', 'name' => 'Toko Cabang Test']);
        $branch = Branch::factory()->create(['work_location_id' => $this->branchLocation->id, 'code' => 'BR-TEST']);
        $this->branchBin = WarehouseLocation::factory()->create([
            'branch_id' => $branch->id,
            'code' => 'BIN-KASIR',
            'full_code' => 'BR-TEST-BIN-KASIR',
            'type' => 'bin',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'sku' => 'PRD-TEST-001',
            'status' => 'active',
            'cost_price' => '10000.00',
        ]);

        foreach ([$this->head, $this->staff] as $user) {
            $user->workLocations()->attach($this->warehouseLocation->id, ['is_default' => true, 'is_active' => true]);
            $user->workLocations()->attach($this->branchLocation->id, ['is_default' => false, 'is_active' => true]);
        }
    }

    public function test_user_with_permission_can_open_import_page_and_download_template(): void
    {
        $this->actingAs($this->head)
            ->get(route('warehouse.opening-stocks.import.index'))
            ->assertOk()
            ->assertSee('Import Stok Produk')
            ->assertSee('Langkah 1: Pilih Lokasi Induk')
            ->assertSee('Langkah 2: Format & Unggah File Excel');

        $this->actingAs($this->head)
            ->get(route('warehouse.opening-stocks.import.template'))
            ->assertOk()
            ->assertDownload('template-import-stok-produk.xlsx');
    }

    public function test_preview_requires_work_location_id_and_valid_file(): void
    {
        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), []);

        $response->assertSessionHasErrors(['work_location_id', 'file']);
    }

    public function test_preview_validates_rows_and_saves_to_session_for_warehouse(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok_gudang.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'SKU Produk' => $this->product->sku,
                    'Kode Rak/Bin (Opsional)' => $this->warehouseBin->full_code,
                    'Jumlah Stok Awal' => 25,
                    'HPP' => 15000,
                    'Alasan' => 'Stok awal gudang',
                ],
            ], ['SKU Produk', 'Kode Rak/Bin (Opsional)', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
            ExcelFormat::XLSX,
        ));

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), [
                'work_location_id' => $this->warehouseLocation->id,
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('opening_stock_import_preview');

        $preview = session('opening_stock_import_preview');
        $this->assertEmpty($preview['errors']);
        $this->assertEquals(1, $preview['totals']['valid_rows']);
        $this->assertEquals($this->warehouseLocation->id, $preview['extra_data']);
    }

    public function test_preview_validates_rows_and_saves_to_session_for_branch(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok_toko.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'SKU Produk' => $this->product->sku,
                    'Kode Rak/Bin (Opsional)' => $this->branchBin->full_code,
                    'Jumlah Stok Awal' => 15,
                    'HPP' => 12000,
                    'Alasan' => 'Saldo awal toko',
                ],
            ], ['SKU Produk', 'Kode Rak/Bin (Opsional)', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
            ExcelFormat::XLSX,
        ));

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), [
                'work_location_id' => $this->branchLocation->id,
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('opening_stock_import_preview');

        $preview = session('opening_stock_import_preview');
        $this->assertEmpty($preview['errors']);
        $this->assertEquals(1, $preview['totals']['valid_rows']);
        $this->assertEquals($this->branchLocation->id, $preview['extra_data']);
    }

    public function test_preview_reports_invalid_sku_or_mismatched_bin(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok_invalid.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'SKU Produk' => 'SKU-TIDAK-ADA',
                    'Kode Rak/Bin (Opsional)' => $this->warehouseBin->full_code,
                    'Jumlah Stok Awal' => 10,
                    'HPP' => 5000,
                    'Alasan' => 'Test SKU salah',
                ],
                [
                    'SKU Produk' => $this->product->sku,
                    // Mencoba memakai bin toko padahal import ke gudang
                    'Kode Rak/Bin (Opsional)' => $this->branchBin->full_code,
                    'Jumlah Stok Awal' => 10,
                    'HPP' => 5000,
                    'Alasan' => 'Test bin salah',
                ],
            ], ['SKU Produk', 'Kode Rak/Bin (Opsional)', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
            ExcelFormat::XLSX,
        ));

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), [
                'work_location_id' => $this->warehouseLocation->id,
                'file' => $file,
            ]);

        $response->assertRedirect();
        $preview = session('opening_stock_import_preview');
        $this->assertNotEmpty($preview['errors']);
        $this->assertCount(2, $preview['errors']);
        $this->assertStringContainsString('SKU produk', $preview['errors'][0]);
        $this->assertStringContainsString('kode lokasi rak/bin', $preview['errors'][1]);
    }

    public function test_commit_updates_stock_on_hand_and_hpp(): void
    {
        $preview = [
            'type' => 'opening_stocks',
            'label' => 'Stok Awal',
            'headers' => ['product_sku', 'warehouse_location_code', 'base_quantity', 'hpp', 'reason'],
            'header_labels' => [
                'product_sku' => 'SKU Produk',
                'warehouse_location_code' => 'Kode Rak/Bin (Opsional)',
                'base_quantity' => 'Jumlah Stok Awal',
                'hpp' => 'HPP',
                'reason' => 'Alasan',
            ],
            'rows' => [
                [
                    'product_sku' => $this->product->sku,
                    'warehouse_location_code' => $this->warehouseBin->full_code,
                    'base_quantity' => '30.0000',
                    'hpp' => '17500.00',
                    'reason' => 'Saldo awal verifikasi',
                ],
            ],
            'errors' => [],
            'totals' => ['rows' => 1, 'valid_rows' => 1, 'invalid_rows' => 0],
            'extra_data' => $this->warehouseLocation->id,
        ];

        session(['opening_stock_import_preview' => $preview]);

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.commit'));

        $response->assertRedirect(route('warehouse.opening-stocks.import.index'));
        $response->assertSessionHas('notification');

        $stock = Stock::query()
            ->where('product_id', $this->product->id)
            ->where('work_location_id', $this->warehouseLocation->id)
            ->where('warehouse_location_id', $this->warehouseBin->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals('30.0000', $stock->quantity_on_hand);

        $this->product->refresh();
        $this->assertEquals('17500.00', (string) $this->product->cost_price);
    }

    public function test_cancel_clears_session_preview(): void
    {
        session(['opening_stock_import_preview' => ['type' => 'opening_stocks']]);

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.cancel'));

        $response->assertRedirect(route('warehouse.opening-stocks.import.index'));
        $this->assertNull(session('opening_stock_import_preview'));
    }

    private function createUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
