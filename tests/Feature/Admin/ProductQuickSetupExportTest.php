<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\StockBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductQuickSetupExportTest extends TestCase
{
    use RefreshDatabase;

    private User $kepalaGudang;

    private User $unauthorizedUser;

    private WorkLocation $gudang;

    private WorkLocation $otherLocation;

    private Unit $pcs;

    private Unit $lsn;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->gudang = WorkLocation::factory()->create([
            'name' => 'Gudang Pusat',
            'type' => 'warehouse',
            'is_active' => true,
        ]);
        Warehouse::factory()->create([
            'work_location_id' => $this->gudang->id,
            'name' => 'Gudang Pusat Fisik',
            'is_active' => true,
        ]);

        $this->otherLocation = WorkLocation::factory()->create([
            'name' => 'Cabang Lain',
            'type' => 'branch',
            'is_active' => true,
        ]);
        Branch::factory()->create([
            'work_location_id' => $this->otherLocation->id,
            'name' => 'Cabang Lain Fisik',
            'is_active' => true,
        ]);

        $this->pcs = Unit::factory()->create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'is_active' => true]);
        $this->lsn = Unit::factory()->create(['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn', 'is_active' => true]);

        $this->category = ProductCategory::factory()->create(['name' => 'Kebutuhan Harian', 'is_active' => true]);

        $this->kepalaGudang = User::factory()->create(['is_active' => true]);
        $this->kepalaGudang->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->kepalaGudang->workLocations()->attach($this->gudang->id, ['is_default' => true]);

        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.products.quick-setup.export'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.products.quick-setup.export'))
            ->assertForbidden();
    }

    public function test_user_cannot_export_unpermitted_location(): void
    {
        $this->actingAs($this->kepalaGudang)
            ->get(route('admin.products.quick-setup.export', ['work_location_id' => $this->otherLocation->id]))
            ->assertForbidden();
    }

    public function test_can_export_complete_quick_setup_csv(): void
    {
        $completeProduct = Product::factory()->create([
            'name' => 'Kopi Arabika Premium',
            'sku' => 'KOP-ARB-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 10000,
            'minimum_price' => 12000,
            'status' => 'active',
            'attributes' => [
                'quick_setup' => [
                    'locations' => [
                        $this->gudang->id => [
                            'hpp_unit_id' => $this->lsn->id,
                            'hpp_amount' => '120000.00',
                            'stock_unit_id' => $this->lsn->id,
                            'stock_qty' => '10.00',
                            'sell_unit_id' => $this->lsn->id,
                            'min_price_amount' => '144000.00',
                        ],
                    ],
                ],
            ],
        ]);

        ProductBarcode::query()->create([
            'product_id' => $completeProduct->id,
            'code' => '8991234567890',
            'is_primary' => true,
        ]);

        StockBatch::query()->create([
            'product_id' => $completeProduct->id,
            'work_location_id' => $this->gudang->id,
            'source_type' => 'opening_stock',
            'unit_id' => $completeProduct->base_unit_id,
            'conversion_factor_snapshot' => '1.000000',
            'batch_no' => 'BATCH-OPENING-001',
            'received_at' => now()->toDateString(),
            'cost_price' => '10000.00',
            'quantity_on_hand' => '120.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'status' => 'active',
        ]);

        ProductPrice::query()->create([
            'product_id' => $completeProduct->id,
            'work_location_id' => $this->gudang->id,
            'channel' => 'retail',
            'price_ring' => 'retail',
            'recommended_price' => '12000.00',
            'minimum_qty' => '1.0000',
            'status' => 'active',
        ]);

        $incompleteProduct = Product::factory()->create([
            'name' => 'Teh Celup Melati',
            'sku' => 'TEH-MEL-002',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'minimum_price' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->kepalaGudang)
            ->get(route('admin.products.quick-setup.export', [
                'work_location_id' => $this->gudang->id,
                'completeness' => 'complete',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV harus diawali UTF-8 BOM untuk kompatibilitas Excel.');
        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString('Nama Produk', $content);
        $this->assertStringContainsString('Kategori', $content);
        $this->assertStringContainsString('Barcode', $content);
        $this->assertStringContainsString('HPP Modal per Pcs', $content);
        $this->assertStringContainsString('Total Stok Fisik', $content);
        $this->assertStringContainsString('Harga Jual Min per Pcs', $content);
        $this->assertStringContainsString('KOP-ARB-001', $content);
        $this->assertStringContainsString('Kopi Arabika Premium', $content);
        $this->assertStringContainsString('8991234567890', $content);
        $this->assertStringContainsString('Lengkap', $content);

        // Produk yang belum lengkap tidak boleh muncul saat filter completeness = complete
        $this->assertStringNotContainsString('TEH-MEL-002', $content);
        $this->assertStringNotContainsString('Teh Celup Melati', $content);
    }

    public function test_can_export_all_quick_setup_products(): void
    {
        $product1 = Product::factory()->create([
            'name' => 'Beras Pandan Wangi',
            'sku' => 'BRS-PDN-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->kepalaGudang)
            ->get(route('admin.products.quick-setup.export', [
                'work_location_id' => $this->gudang->id,
                'completeness' => 'all',
            ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('BRS-PDN-001', $content);
        $this->assertStringContainsString('Beras Pandan Wangi', $content);
    }

    public function test_can_export_xlsx_format(): void
    {
        Product::factory()->create([
            'name' => 'Minyak Goreng 2L',
            'sku' => 'MYK-GRG-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->kepalaGudang)
            ->get(route('admin.products.quick-setup.export', [
                'work_location_id' => $this->gudang->id,
                'completeness' => 'all',
                'format' => 'xlsx',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }
}
