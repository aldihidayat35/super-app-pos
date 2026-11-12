<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductQuickSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $kepalaGudang;

    private User $kepalaToko;

    private User $unauthorizedUser;

    private WorkLocation $gudang;

    private WorkLocation $toko;

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

        $this->toko = WorkLocation::factory()->create([
            'name' => 'Toko Cabang 1',
            'type' => 'branch',
            'is_active' => true,
        ]);
        Branch::factory()->create([
            'work_location_id' => $this->toko->id,
            'name' => 'Toko Cabang 1 Fisik',
            'is_active' => true,
        ]);

        $this->pcs = Unit::factory()->create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'is_active' => true]);
        $this->lsn = Unit::factory()->create(['code' => 'LSN', 'name' => 'Lusin', 'symbol' => 'lsn', 'is_active' => true]);

        $this->category = ProductCategory::factory()->create(['name' => 'Makanan Ringan', 'is_active' => true]);

        // Kepala Gudang
        $this->kepalaGudang = User::factory()->create(['is_active' => true]);
        $this->kepalaGudang->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->kepalaGudang->workLocations()->attach($this->gudang->id, ['is_default' => true]);

        // Kepala Toko
        $this->kepalaToko = User::factory()->create(['is_active' => true]);
        $this->kepalaToko->assignRole(Role::findOrCreate('kepala_toko'));
        $this->kepalaToko->workLocations()->attach($this->toko->id, ['is_default' => true]);

        // Unauthorized user
        $this->unauthorizedUser = User::factory()->create(['is_active' => true]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.products.quick-setup.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.products.quick-setup.index'))
            ->assertForbidden();
    }

    public function test_kepala_gudang_can_access_quick_setup_for_permitted_location(): void
    {
        Product::factory()->create([
            'name' => 'Biskuit Cokelat',
            'sku' => 'BSK-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->kepalaGudang)
            ->get(route('admin.products.quick-setup.index', ['work_location_id' => $this->gudang->id]));

        $response->assertOk()
            ->assertSee('Setup Operasional Cepat Produk')
            ->assertSee('Gudang Pusat')
            ->assertSee('Biskuit Cokelat')
            ->assertSee('BSK-001')
            ->assertSee('Harga Jual Minimum (Anti-Rugi)');
    }

    public function test_kepala_toko_cannot_access_unpermitted_location(): void
    {
        $this->actingAs($this->kepalaToko)
            ->get(route('admin.products.quick-setup.index', ['work_location_id' => $this->gudang->id]))
            ->assertForbidden();
    }

    public function test_save_single_row_calculates_retail_cost_and_allocates_opening_stock(): void
    {
        $product = Product::factory()->create([
            'name' => 'Kopi Sachet Moka',
            'sku' => 'KOP-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'status' => 'active',
        ]);

        $payload = [
            'work_location_id' => $this->gudang->id,
            'product_id' => $product->id,
            'hpp_unit_id' => $this->lsn->id,
            'hpp_amount' => 120000,
            'stock_unit_id' => $this->lsn->id,
            'stock_qty' => 2,
            'prices' => [
                'ring_retail' => ['min' => 13000, 'recommended' => 15000, 'max' => 18000],
                'ring_b2b' => ['min' => 135000, 'recommended' => 140000, 'max' => 150000],
                'ring_pos' => ['min' => 13000, 'recommended' => 15000, 'max' => 18000],
            ],
            'unlimited' => 1,
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'product_id' => $product->id,
                    'retail_cost_price' => '10000.00',
                    'initial_stock_base_qty' => '24.0000',
                    'stock_initialized' => true,
                ],
            ]);

        // Verifikasi Master Product
        $product->refresh();
        $this->assertEquals('10000.00', (string) $product->cost_price);
        $this->assertEquals($this->pcs->id, (int) $product->base_unit_id);

        // Verifikasi Batch Stok Awal FIFO (2 Lusin = 24 pcs @ Rp 10.000)
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'work_location_id' => $this->gudang->id,
            'source_type' => 'opening_stock',
            'unit_id' => $this->lsn->id,
            'conversion_factor_snapshot' => '12.000000',
            'cost_price' => '10000.00',
            'quantity_on_hand' => '24.0000',
        ]);

        // Verifikasi Saldo Stocks
        $stock = Stock::where('product_id', $product->id)
            ->where('work_location_id', $this->gudang->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals('24.0000', (string) $stock->quantity_on_hand);
        $this->assertEquals('240000.00', (string) $stock->cost_value);

        // Verifikasi Ketiga Ring Harga di product_prices (Semua channel tersinkron ke single minimum price)
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->gudang->id,
            'channel' => 'retail',
            'recommended_price' => '15000.00',
            'min_price' => '15000.00',
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->gudang->id,
            'channel' => 'b2b',
            'recommended_price' => '15000.00',
            'min_price' => '15000.00',
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->gudang->id,
            'channel' => 'pos',
            'recommended_price' => '15000.00',
            'min_price' => '15000.00',
        ]);
    }

    public function test_save_single_row_with_selling_unit_and_anti_loss_rule(): void
    {
        $product = Product::factory()->create([
            'name' => 'Kopi Kemasan Grosir',
            'sku' => 'KOP-GRO-01',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'status' => 'active',
        ]);

        // HPP 1 Lusin = 120.000 (10.000 / pcs)
        // Jual 1 Lusin = 150.000 (12.500 / pcs) -> Di atas HPP
        $payload = [
            'work_location_id' => $this->gudang->id,
            'product_id' => $product->id,
            'hpp_unit_id' => $this->lsn->id,
            'hpp_amount' => 120000,
            'stock_unit_id' => $this->pcs->id,
            'stock_qty' => 10,
            'sell_unit_id' => $this->lsn->id,
            'min_price_amount' => 150000,
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'product_id' => $product->id,
                    'retail_cost_price' => '10000.00',
                    'retail_min_price' => '12500.00',
                ],
            ]);

        $product->refresh();
        $this->assertEquals('10000.00', (string) $product->cost_price);
        $this->assertEquals('12500.00', (string) $product->minimum_price);
    }

    public function test_save_single_row_blocks_price_below_cost_price(): void
    {
        $product = Product::factory()->create([
            'name' => 'Sabun Mandi Murah',
            'sku' => 'SBN-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'status' => 'active',
        ]);

        // HPP 1 Pcs = 5.000
        // Jual 1 Pcs = 4.000 (RUGI! Di bawah modal)
        $payload = [
            'work_location_id' => $this->gudang->id,
            'product_id' => $product->id,
            'hpp_unit_id' => $this->pcs->id,
            'hpp_amount' => 5000,
            'stock_unit_id' => $this->pcs->id,
            'stock_qty' => 5,
            'sell_unit_id' => $this->pcs->id,
            'min_price_amount' => 4000,
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_price_amount']);
    }

    public function test_save_row_is_idempotent_for_opening_stock(): void
    {
        $product = Product::factory()->create([
            'name' => 'Teh Melati',
            'sku' => 'TEH-001',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => 0,
            'status' => 'active',
        ]);

        $payload = [
            'work_location_id' => $this->toko->id,
            'product_id' => $product->id,
            'hpp_unit_id' => $this->lsn->id,
            'hpp_amount' => 60000,
            'stock_unit_id' => $this->lsn->id,
            'stock_qty' => 5,
            'prices' => [
                'ring_retail' => ['min' => 6000, 'recommended' => 7000, 'max' => 8000],
                'ring_b2b' => ['min' => 65000, 'recommended' => 70000, 'max' => 75000],
                'ring_pos' => ['min' => 6000, 'recommended' => 7000, 'max' => 8000],
            ],
            'unlimited' => 1,
        ];

        // First Save
        $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload)
            ->assertOk();

        $batchesCount = StockBatch::where('product_id', $product->id)->where('work_location_id', $this->toko->id)->count();
        $this->assertEquals(1, $batchesCount);

        // Second Save (perubahan harga acuan)
        $payload['prices']['ring_retail']['recommended'] = 7500;
        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'stock_initialized' => false,
                ],
            ]);

        // Stock batch count must remain 1 (not duplicated!)
        $this->assertEquals(1, StockBatch::where('product_id', $product->id)->where('work_location_id', $this->toko->id)->count());

        // Price was updated to new value
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'work_location_id' => $this->toko->id,
            'channel' => 'retail',
            'recommended_price' => '7500.00',
        ]);
    }

    public function test_batch_save_processes_multiple_products(): void
    {
        $product1 = Product::factory()->create(['name' => 'Item 1', 'sku' => 'ITM-01', 'category_id' => $this->category->id, 'base_unit_id' => $this->pcs->id, 'status' => 'active']);
        $product2 = Product::factory()->create(['name' => 'Item 2', 'sku' => 'ITM-02', 'category_id' => $this->category->id, 'base_unit_id' => $this->pcs->id, 'status' => 'active']);

        $payload = [
            'work_location_id' => $this->gudang->id,
            'rows' => [
                [
                    'product_id' => $product1->id,
                    'hpp_unit_id' => $this->lsn->id,
                    'hpp_amount' => 24000,
                    'stock_unit_id' => $this->lsn->id,
                    'stock_qty' => 2,
                    'prices' => [
                        'ring_retail' => ['recommended' => 3000],
                        'ring_b2b' => ['recommended' => 30000],
                        'ring_pos' => ['recommended' => 3000],
                    ],
                ],
                [
                    'product_id' => $product2->id,
                    'hpp_unit_id' => $this->pcs->id,
                    'hpp_amount' => 5000,
                    'stock_unit_id' => $this->pcs->id,
                    'stock_qty' => 50,
                    'prices' => [
                        'ring_retail' => ['recommended' => 7000],
                        'ring_b2b' => ['recommended' => 6500],
                        'ring_pos' => ['recommended' => 7000],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.batch-save'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'saved_count' => 2,
                'failed_count' => 0,
            ]);

        $this->assertEquals('2000.00', (string) $product1->fresh()->cost_price);
        $this->assertEquals('5000.00', (string) $product2->fresh()->cost_price);
    }

    public function test_saved_units_and_amounts_are_persisted_in_product_attributes_and_retained_on_refresh(): void
    {
        $product = Product::factory()->create([
            'name' => 'Kopi Arabika Kemasan',
            'sku' => 'KOP-ARB-01',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
            'cost_price' => 15000,
        ]);

        $payload = [
            'product_id' => $product->id,
            'work_location_id' => $this->toko->id,
            'hpp_unit_id' => $this->pcs->id,
            'hpp_amount' => 15000,
            'stock_unit_id' => $this->pcs->id,
            'stock_qty' => 10,
            'prices' => [
                'ring_retail' => ['min' => 16000, 'recommended' => 18000, 'max' => 20000],
                'ring_b2b' => ['min' => 14000, 'recommended' => 16000, 'max' => 18000],
                'ring_pos' => ['min' => 16000, 'recommended' => 18000, 'max' => 20000],
            ],
            'unlimited' => true,
        ];

        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload);

        $response->assertOk();

        // Cek bahwa attributes['quick_setup'] menyimpan unit PCS yang dipilih
        $freshProduct = $product->fresh();
        $this->assertNotNull($freshProduct->attributes);
        $this->assertEquals($this->pcs->id, $freshProduct->attributes['quick_setup']['hpp_unit_id']);
        $this->assertEquals($this->pcs->id, $freshProduct->attributes['quick_setup']['stock_unit_id']);

        // Akses halaman index lagi untuk simulasi refresh
        $getIndex = $this->actingAs($this->kepalaToko)
            ->get(route('admin.products.quick-setup.index', ['work_location_id' => $this->toko->id, 'completeness' => 'all']));

        $getIndex->assertOk();
        $getIndex->assertSee('Kopi Arabika Kemasan');
    }

    public function test_quick_setup_saves_barcode_and_validates_uniqueness(): void
    {
        $product1 = Product::factory()->create([
            'name' => 'Mangkok Ayam Jago',
            'sku' => 'MKK-01',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
            'cost_price' => 5000,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Gelas Kaca Bening',
            'sku' => 'GLS-01',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
            'cost_price' => 3000,
        ]);

        // Simpan baris dengan barcode untuk product1
        $payload1 = [
            'product_id' => $product1->id,
            'work_location_id' => $this->toko->id,
            'barcode' => '8991234567890',
            'hpp_unit_id' => $this->pcs->id,
            'hpp_amount' => 5000,
            'stock_unit_id' => $this->pcs->id,
            'stock_qty' => 5,
            'prices' => [
                'ring_retail' => ['min' => 6000, 'recommended' => 7000, 'max' => 8000],
                'ring_b2b' => ['min' => 5500, 'recommended' => 6000, 'max' => 6500],
                'ring_pos' => ['min' => 6000, 'recommended' => 7000, 'max' => 8000],
            ],
            'unlimited' => true,
        ];

        $response1 = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload1);

        $response1->assertOk();
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product1->id,
            'code' => '8991234567890',
            'is_primary' => true,
        ]);

        // Coba simpan product2 dengan barcode yang sama persis -> harus gagal validasi (422)
        $payload2 = [
            'product_id' => $product2->id,
            'work_location_id' => $this->toko->id,
            'barcode' => '8991234567890',
            'hpp_unit_id' => $this->pcs->id,
            'hpp_amount' => 3000,
            'stock_unit_id' => $this->pcs->id,
            'stock_qty' => 5,
            'prices' => [
                'ring_retail' => ['min' => 4000, 'recommended' => 4500, 'max' => 5000],
                'ring_b2b' => ['min' => 3500, 'recommended' => 4000, 'max' => 4500],
                'ring_pos' => ['min' => 4000, 'recommended' => 4500, 'max' => 5000],
            ],
            'unlimited' => true,
        ];

        $response2 = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.save-row'), $payload2);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['barcode']);
    }

    public function test_store_product_ajax_creates_new_product_and_returns_json(): void
    {
        $payload = [
            'name' => 'Keripik Tempe Renyah 200g',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'barcode' => '8999888777666',
        ];

        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.store-product-ajax'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Produk baru berhasil ditambahkan.',
                'product' => [
                    'name' => 'Keripik Tempe Renyah 200g',
                    'category_id' => $this->category->id,
                    'category_name' => $this->category->name,
                    'base_unit_id' => $this->pcs->id,
                    'base_unit_name' => $this->pcs->name,
                    'barcode' => '8999888777666',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Keripik Tempe Renyah 200g',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('product_barcodes', [
            'code' => '8999888777666',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('product_units', [
            'unit_id' => $this->pcs->id,
            'conversion_factor' => 1,
            'is_base' => true,
        ]);
    }

    public function test_store_product_ajax_auto_generates_sku_when_sku_is_empty(): void
    {
        $payload = [
            'name' => 'Wafer Cokelat Mini',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.store-product-ajax'), $payload);

        $response->assertOk();
        $productData = $response->json('product');
        $this->assertNotEmpty($productData['sku']);
        $this->assertStringStartsWith('PRD', $productData['sku']);

        $this->assertDatabaseHas('products', [
            'name' => 'Wafer Cokelat Mini',
            'sku' => $productData['sku'],
        ]);
    }

    public function test_store_product_ajax_rejects_duplicate_barcode(): void
    {
        $existingProduct = Product::factory()->create(['base_unit_id' => $this->pcs->id, 'category_id' => $this->category->id]);
        $existingProduct->barcodes()->create(['code' => '8991112223334', 'is_primary' => true]);

        $payload = [
            'name' => 'Produk Baru Barcode Sama',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'barcode' => '8991112223334',
        ];

        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.store-product-ajax'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barcode']);
    }

    public function test_store_product_ajax_validates_required_fields(): void
    {
        $payload = [
            'name' => '',
            'category_id' => 999999,
            'base_unit_id' => 999999,
        ];

        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.store-product-ajax'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'category_id', 'base_unit_id']);
    }

    public function test_store_product_ajax_unauthorized_user_is_forbidden(): void
    {
        $payload = [
            'name' => 'Produk Ilegal',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
        ];

        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.products.quick-setup.store-product-ajax'), $payload);

        $response->assertForbidden();
    }

    public function test_bulk_store_products_creates_multiple_products(): void
    {
        $payload = [
            'products' => [
                [
                    'name' => 'Kopi Kapal Mawar 100g',
                    'category_id' => $this->category->id,
                    'base_unit_id' => $this->pcs->id,
                    'barcode' => '8998881112223',
                ],
                [
                    'name' => 'Gula Pasir Kristal 1kg',
                    'category_id' => $this->category->id,
                    'base_unit_id' => $this->pcs->id,
                    'barcode' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->kepalaToko)
            ->postJson(route('admin.products.quick-setup.bulk-store'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'created_count' => 2,
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Kopi Kapal Mawar 100g',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Gula Pasir Kristal 1kg',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
        ]);

        $this->assertDatabaseHas('product_barcodes', [
            'code' => '8998881112223',
        ]);
    }

    public function test_bulk_store_products_validates_barcode_uniqueness(): void
    {
        // 1. Duplikasi barcode di dalam payload sendiri
        $payloadInternalDuplicate = [
            'products' => [
                [
                    'name' => 'Produk A',
                    'category_id' => $this->category->id,
                    'base_unit_id' => $this->pcs->id,
                    'barcode' => '999888777',
                ],
                [
                    'name' => 'Produk B',
                    'category_id' => $this->category->id,
                    'base_unit_id' => $this->pcs->id,
                    'barcode' => '999888777',
                ],
            ],
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.bulk-store'), $payloadInternalDuplicate);

        $response->assertStatus(422);

        // 2. Barcode sudah ada di database
        $existingProduct = Product::factory()->create(['base_unit_id' => $this->pcs->id, 'category_id' => $this->category->id]);
        $existingProduct->barcodes()->create(['code' => '777666555', 'is_primary' => true]);

        $payloadDbDuplicate = [
            'products' => [
                [
                    'name' => 'Produk C',
                    'category_id' => $this->category->id,
                    'base_unit_id' => $this->pcs->id,
                    'barcode' => '777666555',
                ],
            ],
        ];

        $responseDb = $this->actingAs($this->kepalaGudang)
            ->postJson(route('admin.products.quick-setup.bulk-store'), $payloadDbDuplicate);

        $responseDb->assertStatus(422);
    }
}
