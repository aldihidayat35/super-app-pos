<?php

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $kepalaGudang;

    private WorkLocation $location;

    private Supplier $supplier;

    private Unit $pcs;

    private Unit $lsn;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->location = WorkLocation::create([
            'name' => 'Toko Cabang Utama',
            'code' => 'TKO-01',
            'type' => 'branch',
            'is_active' => true,
        ]);

        $this->kepalaGudang = User::factory()->create(['is_active' => true]);
        $this->kepalaGudang->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->kepalaGudang->workLocations()->attach($this->location->id, ['is_default' => true]);

        $this->supplier = Supplier::create([
            'name' => 'PT Sumber Pangan Sejahtera',
            'code' => 'SUP-001',
            'is_active' => true,
        ]);

        $this->pcs = Unit::create(['name' => 'Pieces', 'code' => 'PCS', 'symbol' => 'pcs', 'is_active' => true]);
        $this->lsn = Unit::create(['name' => 'Lusin', 'code' => 'LSN', 'symbol' => 'lsn', 'is_active' => true]);
        $this->category = ProductCategory::create(['name' => 'Makanan & Minuman', 'code' => 'FNB', 'is_active' => true]);
    }

    private function createProduct(string $name, string $sku, float $cost = 10000, float $minPrice = 12000): Product
    {
        $product = Product::create([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'cost_price' => $cost,
            'minimum_price' => $minPrice,
            'status' => 'active',
            'minimum_order' => 0,
            'minimum_stock' => 0,
            'safety_stock' => 0,
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $this->pcs->id,
            'conversion_factor' => 1,
            'is_base' => true,
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $this->lsn->id,
            'conversion_factor' => 12,
            'is_base' => false,
        ]);

        return $product;
    }

    public function test_page_can_be_opened_by_permitted_staff(): void
    {
        $this->actingAs($this->kepalaGudang)
            ->get(route('warehouse.goods-receipts.create'))
            ->assertOk()
            ->assertSee('Penerimaan Barang Supplier')
            ->assertSee('PT Sumber Pangan Sejahtera')
            ->assertSee('Toko Cabang Utama');
    }

    public function test_user_cannot_create_receipt_for_unpermitted_location(): void
    {
        $otherLocation = WorkLocation::create([
            'name' => 'Gudang Terlarang',
            'code' => 'GDG-OTHER',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        $product = $this->createProduct('Kopi Sachet', 'SKU-001');

        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $otherLocation->id,
            'received_at' => now()->toDateString(),
            'actual_freight_cost' => 0,
            'actual_additional_cost' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 10,
                    'quantity_accepted' => 10,
                    'unit_price' => 10000,
                    'sale_price' => 15000,
                ],
            ],
        ];

        $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload)
            ->assertSessionHasErrors(['destination_work_location_id']);
    }

    public function test_create_direct_goods_receipt_draft_without_po(): void
    {
        $product = $this->createProduct('Kopi Sachet', 'SKU-001');

        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'received_at' => now()->toDateString(),
            'delivery_note_number' => 'SJ-2026/001',
            'actual_freight_cost' => 15000,
            'actual_additional_cost' => 5000,
            'notes' => 'Penerimaan draft tanpa PO',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 100,
                    'quantity_accepted' => 95,
                    'quantity_damaged' => 3,
                    'quantity_rejected' => 2,
                    'unit_price' => 8000,
                    'sale_price' => 12000,
                ],
            ],
            'action' => 'draft',
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('goods_receipts', [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'purchase_order_id' => null,
            'status' => 'draft',
            'delivery_note_number' => 'SJ-2026/001',
        ]);

        $this->assertDatabaseHas('goods_receipt_items', [
            'purchase_order_item_id' => null,
            'product_id' => $product->id,
            'quantity_received' => 100,
            'quantity_accepted' => 95,
            'quantity_damaged' => 3,
            'quantity_rejected' => 2,
        ]);

        // Stok belum bertambah karena status masih draft
        $this->assertNull(Stock::query()->where('product_id', $product->id)->first());
    }

    public function test_post_direct_goods_receipt_updates_stock_fifo_batches_and_landed_costs(): void
    {
        $product = $this->createProduct('Kopi Sachet', 'SKU-001', 8000, 10000);

        // Datang 100 pcs @ Rp 8.000 (Subtotal Rp 800.000).
        // 90 pcs accepted, 10 pcs damaged.
        // Total Ongkir Rp 90.000 -> dibagikan rata ke 90 pcs accepted = Rp 1.000/pcs.
        // HPP Final = Rp 8.000 + Rp 1.000 = Rp 9.000/pcs.
        // Harga jual minimum Rp 11.000 (> Rp 9.000).
        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'received_at' => now()->toDateString(),
            'actual_freight_cost' => 90000,
            'actual_additional_cost' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 100,
                    'quantity_accepted' => 90,
                    'quantity_damaged' => 10,
                    'quantity_rejected' => 0,
                    'unit_price' => 8000,
                    'sale_price' => 11000,
                ],
            ],
            'action' => 'post',
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload);

        $response->assertRedirect();

        // 1. Goods receipt posted
        $this->assertDatabaseHas('goods_receipts', [
            'purchase_order_id' => null,
            'status' => 'posted',
        ]);

        // 2. Stok fisik on_hand = 100 (90 accepted + 10 damaged), stok rusak = 10, stok available = 90
        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(100, (float) $stock->quantity_on_hand);
        $this->assertEquals(10, (float) $stock->quantity_damaged);
        $this->assertEquals(90, (float) $stock->available_quantity);

        // 3. FIFO Layer terbentuk dengan HPP Final Rp 9.000
        $batch = StockBatch::query()->where('product_id', $product->id)->where('status', 'active')->firstOrFail();
        $this->assertEquals(9000, (float) $batch->cost_price);
        $this->assertEquals(11000, (float) $batch->entry_sale_price_snapshot);

        // 4. Mutasi stok tercatat
        $this->assertDatabaseHas('stock_mutations', [
            'product_id' => $product->id,
            'reference_type' => 'goods_receipt',
            'mutation_type' => 'receive',
        ]);

        // 5. Histori HPP tercatat
        $this->assertDatabaseHas('product_cost_histories', [
            'product_id' => $product->id,
            'hpp_after' => 9000,
        ]);
    }

    public function test_proportional_landed_cost_allocation_math(): void
    {
        $prodA = $this->createProduct('Produk Murah', 'SKU-A', 10000, 15000);
        $prodB = $this->createProduct('Produk Mahal', 'SKU-B', 30000, 40000);

        // Prod A: 10 pcs @ Rp 10.000 = Rp 100.000 (25% nilai)
        // Prod B: 10 pcs @ Rp 30.000 = Rp 300.000 (75% nilai)
        // Total Pembelian = Rp 400.000
        // Total Ongkir = Rp 40.000
        // Alokasi Ongkir:
        // Prod A: 25% x 40.000 = Rp 10.000 / 10 pcs = Rp 1.000/pcs -> HPP Final = Rp 11.000
        // Prod B: 75% x 40.000 = Rp 30.000 / 10 pcs = Rp 3.000/pcs -> HPP Final = Rp 33.000
        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'received_at' => now()->toDateString(),
            'actual_freight_cost' => 40000,
            'actual_additional_cost' => 0,
            'items' => [
                [
                    'product_id' => $prodA->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 10,
                    'quantity_accepted' => 10,
                    'unit_price' => 10000,
                    'sale_price' => 15000,
                ],
                [
                    'product_id' => $prodB->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 10,
                    'quantity_accepted' => 10,
                    'unit_price' => 30000,
                    'sale_price' => 40000,
                ],
            ],
            'action' => 'post',
        ];

        $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload)
            ->assertRedirect();

        $batchA = StockBatch::query()->where('product_id', $prodA->id)->firstOrFail();
        $this->assertEquals(11000, (float) $batchA->cost_price);

        $batchB = StockBatch::query()->where('product_id', $prodB->id)->firstOrFail();
        $this->assertEquals(33000, (float) $batchB->cost_price);
    }

    public function test_anti_loss_rule_rejects_sale_price_below_or_equal_to_hpp_final(): void
    {
        $product = $this->createProduct('Kopi Sachet', 'SKU-001', 8000, 10000);

        // HPP Beli 8.000 + Ongkir 2.000 = HPP Final Rp 10.000
        // Sale price diisi Rp 9.500 (di bawah modal HPP Final) -> DITOLAK
        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'received_at' => now()->toDateString(),
            'actual_freight_cost' => 20000,
            'actual_additional_cost' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 10,
                    'quantity_accepted' => 10,
                    'unit_price' => 8000,
                    'sale_price' => 9500, // Di bawah HPP Final 10.000
                ],
            ],
            'action' => 'post',
        ];

        $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload)
            ->assertSessionHas('notification'); // ServiceException handled with error notification

        $this->assertNull(Stock::query()->where('product_id', $product->id)->first());
    }

    public function test_anti_loss_rule_rejects_sale_price_below_highest_active_batch_cost(): void
    {
        $product = $this->createProduct('Kopi Sachet', 'SKU-001', 8000, 16000);

        // Buat batch lama yang masih aktif dengan HPP Rp 15.000
        StockBatch::create([
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'cost_price' => 15000,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'quantity_damaged' => 0,
            'conversion_factor_snapshot' => 1,
            'batch_no' => 'BATCH-OLD',
            'received_at' => now()->subDays(5)->toDateString(),
            'status' => 'active',
            'source_type' => 'manual',
        ]);

        // Penerimaan baru: HPP Beli Rp 8.000 + Ongkir 0 = HPP Final Rp 8.000
        // Jika kepala toko mencoba set harga jual Rp 12.000:
        // Meskipun di atas HPP baru (8.000), tapi di bawah batch lama (15.000) -> DITOLAK
        $payload = [
            'supplier_id' => $this->supplier->id,
            'destination_work_location_id' => $this->location->id,
            'received_at' => now()->toDateString(),
            'actual_freight_cost' => 0,
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $this->pcs->id,
                    'quantity_received' => 10,
                    'quantity_accepted' => 10,
                    'unit_price' => 8000,
                    'sale_price' => 12000, // < 15.000 (batch aktif lama)
                ],
            ],
            'action' => 'post',
        ];

        $this->actingAs($this->kepalaGudang)
            ->post(route('warehouse.goods-receipts.store'), $payload)
            ->assertSessionHas('notification');
    }

    public function test_quick_store_product_ajax_creates_product_and_returns_json(): void
    {
        $payload = [
            'name' => 'Kopi Robusta Lampung',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pcs->id,
            'sku' => 'SKU-ROBUSTA-01',
            'barcode' => '8991234567890',
        ];

        $response = $this->actingAs($this->kepalaGudang)
            ->postJson(route('warehouse.goods-receipts.store-product-ajax'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'product' => [
                    'name' => 'Kopi Robusta Lampung',
                    'sku' => 'SKU-ROBUSTA-01',
                    'barcode' => '8991234567890',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Kopi Robusta Lampung',
            'sku' => 'SKU-ROBUSTA-01',
        ]);

        $this->assertDatabaseHas('product_barcodes', [
            'code' => '8991234567890',
        ]);
    }
}
