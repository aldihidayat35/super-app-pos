<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductImageService;
use App\Services\Product\UnitConversionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;
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
                ['code' => '8991234567890', 'type' => 'barcode', 'unit_id' => $pack->id],
            ],
        ]);

        $response->assertRedirect();
        $product = Product::query()->where('sku', 'PRD-TEST-001')->firstOrFail();
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_id' => $pcs->id, 'is_base' => true]);
        $packProductUnit = ProductUnit::query()->where('product_id', $product->id)->where('unit_id', $pack->id)->firstOrFail();
        $this->assertDatabaseHas('product_barcodes', ['product_id' => $product->id, 'product_unit_id' => $packProductUnit->id, 'code' => '8991234567890']);
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

    public function test_product_unit_tab_uses_native_metronic_dropdown_with_complete_active_unit_options(): void
    {
        ProductCategory::factory()->create();
        Unit::factory()->create([
            'code' => 'PCS-UI',
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'is_active' => true,
        ]);
        Unit::factory()->create([
            'code' => 'BOX-UI',
            'name' => 'Box',
            'symbol' => 'box',
            'is_active' => true,
        ]);
        Unit::factory()->create([
            'code' => 'OLD-UI',
            'name' => 'Satuan Nonaktif',
            'symbol' => 'old',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertOk()
            ->assertSee('Pilih Satuan')
            ->assertSee('Pieces (pcs)')
            ->assertSee('Box (box)')
            ->assertDontSee('Satuan Nonaktif')
            ->assertSee('data-control="native"', false)
            ->assertSee('data-unit-select', false)
            ->assertSee('product-unit-row', false)
            ->assertSee('ki-outline ki-trash fs-2', false);

        $content = $response->getContent();

        $this->assertStringContainsString('const availableUnits =', $content);
        $this->assertStringNotContainsString('firstSelect', $content);
        $this->assertStringContainsString('availableUnits.forEach', $content);
        $this->assertStringContainsString('rows.length === 1', $content);
    }

    public function test_stock_fields_are_saved_on_product_creation(): void
    {
        $category = ProductCategory::factory()->create();
        $pcs = Unit::factory()->create();

        $form = $this->actingAs($this->admin)->get(route('admin.products.create'));
        $form->assertOk()->assertSee('Harga Dasar')->assertSee('Pengaturan Stok');

        foreach (['minimum_order', 'minimum_stock', 'safety_stock', 'weight', 'volume', 'cost_price', 'minimum_price'] as $field) {
            $this->assertSame(1, substr_count($form->getContent(), 'name="'.$field.'"'), "Field {$field} tidak boleh diduplikasi.");
        }

        $this->assertStringNotContainsString('hidden_minimum_stock', $form->getContent());
        $this->assertStringNotContainsString('STOCK FIELDS SYNC', $form->getContent());

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'PRD-STOCK-001',
            'name' => 'Produk Tes Stok',
            'category_id' => $category->id,
            'base_unit_id' => $pcs->id,
            'status' => 'active',
            'minimum_order' => 5,
            'minimum_stock' => 100,
            'safety_stock' => 20,
            'weight' => 1.5,
            'volume' => 2.5,
            'cost_price' => 50000,
            'minimum_price' => 75000,
            'units' => [
                ['unit_id' => $pcs->id, 'conversion_factor' => 1, 'is_sellable' => 1, 'is_active' => 1],
            ],
        ]);

        $response->assertRedirect();
        $product = Product::query()->where('sku', 'PRD-STOCK-001')->firstOrFail();

        $this->assertSame('5.0000', $product->fresh()->minimum_order);
        $this->assertSame('100.0000', $product->fresh()->minimum_stock);
        $this->assertSame('20.0000', $product->fresh()->safety_stock);
        $this->assertSame('1.5000', $product->fresh()->weight);
        $this->assertSame('2.5000', $product->fresh()->volume);
        $this->assertSame('50000.00', $product->fresh()->cost_price);
        $this->assertSame('75000.00', $product->fresh()->minimum_price);

        $this->actingAs($this->admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Pengaturan Stok Master')
            ->assertSee('Minimum Order')
            ->assertSee('Minimum Stock')
            ->assertSee('Safety Stock')
            ->assertSee('HPP Fallback Master')
            ->assertSee('Min. Harga Jual')
            ->assertSee('Harga jual belum dibuat')
            ->assertSee('Belum ada saldo stok')
            ->assertSee('Belum ada mutasi');

        $this->assertDatabaseMissing('product_prices', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('stocks', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('stock_mutations', ['product_id' => $product->id]);
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

    public function test_standard_dozen_factor_cannot_be_manipulated_when_base_unit_is_pieces(): void
    {
        $category = ProductCategory::factory()->create();
        $pcs = Unit::factory()->create(['code' => 'PCS']);
        $dozen = Unit::factory()->create(['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn']);

        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'GELAS-LSN-001',
            'name' => 'Gelas Lusin',
            'category_id' => $category->id,
            'base_unit_id' => $pcs->id,
            'status' => 'active',
            'minimum_order' => 1,
            'minimum_stock' => 0,
            'safety_stock' => 0,
            'units' => [
                ['unit_id' => $pcs->id, 'conversion_factor' => 1, 'is_sellable' => 1, 'is_active' => 1],
                ['unit_id' => $dozen->id, 'conversion_factor' => 11, 'is_sellable' => 1, 'is_active' => 1],
            ],
        ])->assertSessionHasErrors('units');

        $this->assertDatabaseMissing('products', ['sku' => 'GELAS-LSN-001']);
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

        $this->actingAs($this->admin)
            ->get(route('admin.products.import.index'))
            ->assertOk()
            ->assertSee('Masih ada error validasi')
            ->assertSee('Baris 2');
    }

    public function test_valid_import_preview_survives_page_render_and_can_be_committed(): void
    {
        ProductCategory::factory()->create(['code' => 'UMUM']);
        Unit::factory()->create(['code' => 'PCS']);
        $file = UploadedFile::fake()->createWithContent('produk-valid.csv', "sku,name,category_code,brand_code,base_unit_code,status,minimum_order,minimum_stock,safety_stock\nPRD-IMPORT-VALID,Produk Import Valid,UMUM,,PCS,active,1,10,5\n");

        $this->actingAs($this->admin)
            ->post(route('admin.products.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.products.import.index'))
            ->assertSessionHas('product_import_preview');

        $this->get(route('admin.products.import.index'))
            ->assertOk()
            ->assertSee('Semua baris valid')
            ->assertSee('Commit Import');

        $this->post(route('admin.products.import.commit'))
            ->assertRedirect(route('admin.products.import.index'))
            ->assertSessionHas('notification.type', 'success')
            ->assertSessionMissing('product_import_preview');

        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-IMPORT-VALID',
            'name' => 'Produk Import Valid',
            'status' => 'active',
        ]);
    }

    public function test_view_only_role_cannot_create_product(): void
    {
        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole(Role::findOrCreate('kepala_toko'));

        $this->actingAs($viewer)->get(route('admin.products.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.products.create'))->assertForbidden();
    }

    public function test_product_primary_photo_is_persisted_owned_and_audited(): void
    {
        $product = Product::factory()->create(['main_image_path' => 'products/old.jpg']);
        $first = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/old.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $second = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/new.jpg', 'sort_order' => 2, 'is_primary' => false]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.products.images.primary', [$product, $second]))
            ->assertOk()
            ->assertJsonPath('image_id', $second->id)
            ->assertJsonPath('message', 'Foto utama berhasil diperbarui.');

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame('products/new.jpg', $product->fresh()->main_image_path);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->where('is_primary', true)->count());
        $this->assertDatabaseHas('activity_log', ['subject_id' => $product->id, 'description' => 'product.photo.primary_changed']);

        $otherProduct = Product::factory()->create();
        $otherImage = ProductImage::query()->create(['product_id' => $otherProduct->id, 'path' => 'products/other.jpg']);
        $this->patchJson(route('admin.products.images.primary', [$product, $otherImage]))->assertForbidden();
    }

    public function test_deleting_primary_photo_promotes_first_remaining_photo(): void
    {
        $product = Product::factory()->create(['main_image_path' => 'products/primary.jpg']);
        $primary = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/primary.jpg', 'sort_order' => 1, 'is_primary' => true]);
        $replacement = ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/replacement.jpg', 'sort_order' => 2, 'is_primary' => false]);

        app(ProductImageService::class)->remove($product, $primary, $this->admin);

        $this->assertModelMissing($primary);
        $this->assertTrue($replacement->fresh()->is_primary);
        $this->assertSame('products/replacement.jpg', $product->fresh()->main_image_path);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $product->id, 'description' => 'product.photo.deleted']);
    }

    public function test_product_audit_tab_formats_actions_and_uses_pagination(): void
    {
        $product = Product::factory()->create();
        activity()->causedBy($this->admin)->performedOn($product)->withProperties([
            'old' => ['name' => 'Produk Lama'],
            'attributes' => ['name' => 'Produk Baru'],
        ])->log('product.updated');

        $response = $this->actingAs($this->admin)->get(route('admin.products.show', $product));
        $response->assertOk()
            ->assertSee('Produk Diperbarui')
            ->assertSee('Nama Produk')
            ->assertSee('Produk Lama')
            ->assertSee('Produk Baru')
            ->assertDontSee('product.updated');

        $this->assertSame(1, Activity::query()->where('subject_id', $product->id)->count());
    }

    public function test_import_with_only_product_name_auto_generates_sku_and_defaults_category_and_unit(): void
    {
        $csvRows = ['name,sku,barcode,category_code,brand_code,base_unit_code,minimum_order,minimum_stock,safety_stock'];
        $csvRows[] = 'Piring Keramik Putih Polos,,,,,,,,';
        for ($i = 2; $i <= 10; $i++) {
            $csvRows[] = "Produk Massal Ke-{$i},,,,,,,,";
        }

        $file = UploadedFile::fake()->createWithContent(
            'produk-name-only.csv',
            implode("\n", $csvRows)."\n"
        );

        $this->actingAs($this->admin)
            ->post(route('admin.products.import.preview'), ['file' => $file])
            ->assertRedirect(route('admin.products.import.index'))
            ->assertSessionHas('product_import_preview');

        $preview = session('product_import_preview');
        $this->assertEmpty($preview['errors'] ?? []);
        $this->assertSame(10, $preview['valid_count'] ?? 0);

        $this->actingAs($this->admin)
            ->post(route('admin.products.import.commit'))
            ->assertRedirect(route('admin.products.import.index'))
            ->assertSessionHas('notification.type', 'success');

        $monthPrefix = now()->format('mY');
        $product = Product::query()->where('name', 'Piring Keramik Putih Polos')->first();
        $this->assertNotNull($product);
        $this->assertSame("PRD{$monthPrefix}0001", $product->sku);
        $this->assertSame('Umum', $product->category?->name);
        $this->assertSame('PCS', $product->baseUnit?->code);
        $this->assertSame('0.0000', $product->minimum_order);

        $tenthProduct = Product::query()->where('name', 'Produk Massal Ke-10')->first();
        $this->assertNotNull($tenthProduct);
        $this->assertSame("PRD{$monthPrefix}0010", $tenthProduct->sku);
    }

    public function test_import_template_downloads_two_sheet_spreadsheet(): void
    {
        ProductCategory::factory()->create(['code' => 'DAPUR', 'name' => 'Alat Dapur', 'is_active' => true]);
        Unit::factory()->create(['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.products.import.template'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        $tempFile = (string) tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tempFile, $content);

        $spreadsheet = IOFactory::load($tempFile);
        $this->assertSame(2, $spreadsheet->getSheetCount());
        $this->assertSame('Data Import', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Referensi Sistem', $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame('name', $spreadsheet->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('sku', $spreadsheet->getSheet(0)->getCell('B1')->getValue());

        @unlink($tempFile);
    }

    public function test_super_admin_can_reset_all_products_for_testing(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole(Role::findOrCreate('super_admin'));

        Product::factory()->count(3)->create();
        $this->assertDatabaseCount('products', 3);

        $response = $this->actingAs($superAdmin)->post(route('admin.products.reset-all'));
        $response->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('notification.type', 'success');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_non_super_admin_cannot_reset_all_products(): void
    {
        Product::factory()->create();
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(Role::findOrCreate('staff_gudang'));

        $this->actingAs($staff)->post(route('admin.products.reset-all'))->assertForbidden();
        $this->assertDatabaseCount('products', 1);
    }
}
