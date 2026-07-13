<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\UnitConversionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole(Role::findOrCreate('admin_config'));
    }

    public function test_admin_can_open_product_master_pages(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.product-categories.index'))->assertOk()->assertSee('Kategori');
        $this->get(route('admin.product-brands.index'))->assertOk()->assertSee('Merek');
        $this->get(route('admin.units.index'))->assertOk()->assertSee('Satuan');
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Daftar Produk');
        $this->get(route('admin.products.barcodes.index'))->assertOk()->assertSee('Cetak Barcode');
        $this->get(route('admin.products.import.index'))->assertOk()->assertSee('Import');
    }

    public function test_product_can_be_created_with_photo_unit_and_unique_barcode(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create(['code' => 'UMUM']);
        $brand = ProductBrand::factory()->create();
        $pcs = Unit::factory()->create(['code' => 'PCS']);
        $pack = Unit::factory()->create(['code' => 'PACK']);

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'PRD-TEST-001',
            'name' => 'Produk Test',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $pcs->id,
            'status' => 'active',
            'minimum_order' => 1,
            'minimum_stock' => 10,
            'safety_stock' => 5,
            'main_image' => UploadedFile::fake()->image('produk.jpg'),
            'units' => [
                ['unit_id' => $pcs->id, 'conversion_factor' => 1, 'is_sellable' => 1, 'is_active' => 1],
                ['unit_id' => $pack->id, 'conversion_factor' => 12, 'is_sellable' => 1, 'is_active' => 1],
            ],
            'barcodes' => [
                ['code' => '8991234567890', 'type' => 'barcode'],
            ],
        ]);

        $response->assertRedirect();
        $product = Product::query()->where('sku', 'PRD-TEST-001')->firstOrFail();
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_id' => $pcs->id, 'is_base' => true]);
        $this->assertDatabaseHas('product_barcodes', ['product_id' => $product->id, 'code' => '8991234567890']);
        $this->assertNotNull($product->fresh()->main_image_path);

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'PRD-TEST-002',
            'name' => 'Produk Duplikat Barcode',
            'category_id' => $category->id,
            'base_unit_id' => $pcs->id,
            'status' => 'active',
            'minimum_order' => 1,
            'minimum_stock' => 1,
            'safety_stock' => 1,
            'units' => [['unit_id' => $pcs->id, 'conversion_factor' => 1, 'is_sellable' => 1, 'is_active' => 1]],
            'barcodes' => [['code' => '8991234567890', 'type' => 'barcode']],
        ])->assertSessionHasErrors('barcodes.0.code');
    }

    public function test_unit_conversion_and_locked_factor_are_enforced(): void
    {
        $product = Product::factory()->create();
        $pack = Unit::factory()->create(['code' => 'PACK']);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $pack->id,
            'conversion_factor' => 12,
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
            'is_locked' => true,
        ]);

        $service = app(UnitConversionService::class);
        $this->assertSame('24.0000', $service->toBase('2', $productUnit));

        $this->expectExceptionMessage('tidak boleh diubah');
        $service->syncProductUnits($product, [
            ['unit_id' => $productUnit->unit_id, 'conversion_factor' => 10, 'is_sellable' => true, 'is_active' => true],
        ]);
    }

    public function test_import_preview_reports_invalid_rows(): void
    {
        Unit::factory()->create(['code' => 'PCS']);
        $file = UploadedFile::fake()->createWithContent('produk.csv', "sku,name,category_code,brand_code,base_unit_code,status,minimum_order,minimum_stock,safety_stock\nPRD-1,Produk Import,SALAH,,PCS,active,1,1,1\n");

        $this->actingAs($this->admin)
            ->post(route('admin.products.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.products.import.index'))
            ->assertSessionHas('product_import_preview');

        $preview = session('product_import_preview');
        $this->assertNotEmpty($preview['errors']);
    }

    public function test_view_only_role_cannot_create_product(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(Role::findOrCreate('kepala_toko'));

        $this->actingAs($viewer)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.products.create'))->assertForbidden();
    }
}
