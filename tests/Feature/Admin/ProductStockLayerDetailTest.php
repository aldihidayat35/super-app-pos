<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductStockLayerDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_detail_produk_menampilkan_hpp_berlapis_dan_harga_per_lokasi_untuk_pengguna_berwenang(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin_config'));

        $product = Product::factory()->create(['cost_price' => '999.00']);
        $location = WorkLocation::factory()->create(['name' => 'Gudang Utama', 'code' => 'GDG-UTAMA']);
        $stock = $this->createStock($product, $location, '30.0000');

        $this->createBatch($product, $stock, $location, 'BATCH-LAMA', '10.0000', '10000.00');
        $this->createBatch($product, $stock, $location, 'BATCH-BARU', '20.0000', '12000.00');
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'channel' => 'retail',
            'price_ring' => 'retail',
            'recommended_price' => '16000.00',
            'minimum_qty' => '1.0000',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Lapisan Stok per Lokasi')
            ->assertSee('Gudang Utama')
            ->assertSee('BATCH-LAMA')
            ->assertSee('BATCH-BARU')
            ->assertSee('HPP / Unit Dasar')
            ->assertSee('Rp10.000')
            ->assertSee('Rp12.000')
            ->assertSee('Rp16.000')
            ->assertSee('Rp340.000');
    }

    public function test_detail_produk_membatasi_lapisan_ke_lokasi_pengguna_dan_tidak_mengirim_hpp_tanpa_izin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('products.view');

        $product = Product::factory()->create(['cost_price' => '987654.00']);
        $allowedLocation = WorkLocation::factory()->create(['name' => 'Toko Diizinkan', 'code' => 'TKO-IZIN']);
        $blockedLocation = WorkLocation::factory()->create(['name' => 'Gudang Terlarang', 'code' => 'GDG-BLOK']);
        $user->workLocations()->attach($allowedLocation->id, ['is_default' => true, 'is_active' => true]);

        $allowedStock = $this->createStock($product, $allowedLocation, '5.0000');
        $blockedStock = $this->createStock($product, $blockedLocation, '7.0000');
        $this->createBatch($product, $allowedStock, $allowedLocation, 'BATCH-BOLEH', '5.0000', '12345.00');
        $this->createBatch($product, $blockedStock, $blockedLocation, 'BATCH-RAHASIA', '7.0000', '67890.00');

        $response = $this->actingAs($user)->get(route('admin.products.show', $product));

        $response->assertOk()
            ->assertSee('BATCH-BOLEH')
            ->assertSee('Toko Diizinkan')
            ->assertDontSee('BATCH-RAHASIA')
            ->assertDontSee('Gudang Terlarang')
            ->assertDontSee('HPP / Unit Dasar')
            ->assertDontSee('Nilai Persediaan dari Lapisan HPP')
            ->assertDontSee('Rp12.345')
            ->assertDontSee('Rp67.890')
            ->assertDontSee('Rp987.654');
    }

    private function createStock(Product $product, WorkLocation $location, string $quantity): Stock
    {
        return Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'location_scope_key' => 'work:'.$location->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '0.00',
        ]);
    }

    private function createBatch(
        Product $product,
        Stock $stock,
        WorkLocation $location,
        string $batchNo,
        string $quantity,
        string $costPrice,
    ): StockBatch {
        return StockBatch::query()->create([
            'product_id' => $product->id,
            'stock_id' => $stock->id,
            'work_location_id' => $location->id,
            'source_type' => 'goods_receipt',
            'unit_id' => $product->base_unit_id,
            'conversion_factor_snapshot' => '1.000000',
            'batch_no' => $batchNo,
            'received_at' => now()->toDateString(),
            'cost_price' => $costPrice,
            'entry_sale_price_snapshot' => '16000.00',
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'status' => 'active',
        ]);
    }
}
