<?php

namespace Tests\Feature\Warehouse;

use App\Exports\ArrayReportExport;
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

    private WorkLocation $location;

    private WarehouseLocation $bin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->head = $this->createUser('kepala_gudang');
        $this->staff = $this->createUser('staff_gudang');

        $this->location = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-TEST']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $this->location->id, 'code' => 'WH-TEST']);
        $this->bin = WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'RAK-01',
            'full_code' => 'RAK-01',
            'is_active' => true,
        ]);
        $this->product = Product::factory()->create([
            'sku' => 'PRD-TEST-001',
            'status' => 'active',
            'cost_price' => '10000.00',
        ]);

        foreach ([$this->head, $this->staff] as $user) {
            $user->workLocations()->attach($this->location->id, ['is_default' => true, 'is_active' => true]);
        }
    }

    public function test_user_with_permission_can_open_import_page_and_download_template(): void
    {
        $this->actingAs($this->head)
            ->get(route('warehouse.opening-stocks.import.index'))
            ->assertOk()
            ->assertSee('Import Stok Awal')
            ->assertSee('Template Excel');

        $this->actingAs($this->head)
            ->get(route('warehouse.opening-stocks.import.template'))
            ->assertOk()
            ->assertDownload('template-opening-stocks.xlsx');
    }

    public function test_preview_validates_rows_and_saves_to_session(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok_awal.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'SKU Produk' => $this->product->sku,
                    'Kode Gudang/Cabang' => $this->location->code,
                    'Kode Lokasi Gudang' => $this->bin->full_code,
                    'Jumlah Stok Awal' => 25,
                    'HPP' => 15000,
                    'Alasan' => 'Stok awal gudang',
                ],
            ], ['SKU Produk', 'Kode Gudang/Cabang', 'Kode Lokasi Gudang', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
            ExcelFormat::XLSX,
        ));

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('opening_stock_import_preview');

        $preview = session('opening_stock_import_preview');
        $this->assertEmpty($preview['errors']);
        $this->assertEquals(1, $preview['totals']['valid_rows']);
    }

    public function test_preview_reports_invalid_sku_or_location(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok_awal_invalid.xlsx', Excel::raw(
            new ArrayReportExport([
                [
                    'SKU Produk' => 'SKU-TIDAK-ADA',
                    'Kode Gudang/Cabang' => $this->location->code,
                    'Kode Lokasi Gudang' => $this->bin->full_code,
                    'Jumlah Stok Awal' => 10,
                    'HPP' => 5000,
                    'Alasan' => 'Test error',
                ],
            ], ['SKU Produk', 'Kode Gudang/Cabang', 'Kode Lokasi Gudang', 'Jumlah Stok Awal', 'HPP', 'Alasan']),
            ExcelFormat::XLSX,
        ));

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.preview'), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $preview = session('opening_stock_import_preview');
        $this->assertNotEmpty($preview['errors']);
        $this->assertStringContainsString('SKU produk tidak ditemukan', $preview['errors'][0]);
    }

    public function test_commit_updates_stock_on_hand_and_hpp(): void
    {
        $preview = [
            'type' => 'opening_stocks',
            'label' => 'Stok Awal',
            'headers' => ['product_sku', 'work_location_code', 'warehouse_location_code', 'base_quantity', 'hpp', 'reason'],
            'header_labels' => [
                'product_sku' => 'SKU Produk',
                'work_location_code' => 'Kode Gudang/Cabang',
                'warehouse_location_code' => 'Kode Lokasi Gudang',
                'base_quantity' => 'Jumlah Stok Awal',
                'hpp' => 'HPP',
                'reason' => 'Alasan',
            ],
            'rows' => [
                [
                    'product_sku' => $this->product->sku,
                    'work_location_code' => $this->location->code,
                    'warehouse_location_code' => $this->bin->full_code,
                    'base_quantity' => '30.0000',
                    'hpp' => '17500.00',
                    'reason' => 'Saldo awal verifikasi',
                ],
            ],
            'errors' => [],
            'totals' => ['rows' => 1, 'valid_rows' => 1, 'invalid_rows' => 0],
            'dry_run' => true,
        ];

        session(['opening_stock_import_preview' => $preview]);

        $response = $this->actingAs($this->head)
            ->post(route('warehouse.opening-stocks.import.commit'));

        $response->assertRedirect(route('warehouse.opening-stocks.index'));
        $response->assertSessionHas('notification');

        $stock = Stock::query()
            ->where('product_id', $this->product->id)
            ->where('work_location_id', $this->location->id)
            ->where('warehouse_location_id', $this->bin->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals('30.0000', $stock->quantity_on_hand);

        $this->product->refresh();
        $this->assertEquals('17500.00', (string) $this->product->cost_price);
    }

    private function createUser(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
