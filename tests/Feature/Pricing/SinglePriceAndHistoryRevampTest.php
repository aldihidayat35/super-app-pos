<?php

namespace Tests\Feature\Pricing;

use App\Enums\ProductPriceStatus;
use App\Models\Branch;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Product\ProductQuickSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SinglePriceAndHistoryRevampTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private WorkLocation $location;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_active' => true]);
        $this->superAdmin->assignRole(Role::findOrCreate('super_admin'));

        $this->location = WorkLocation::factory()->create([
            'name' => 'Toko Retail Utama',
            'code' => 'TRU-01',
            'type' => 'branch',
            'is_active' => true,
        ]);

        $this->branch = Branch::factory()->create([
            'name' => 'Cabang Retail Utama',
            'code' => 'CRU-01',
            'work_location_id' => $this->location->id,
            'is_active' => true,
        ]);

        $this->superAdmin->workLocations()->attach($this->location->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_sidebar_navigation_does_not_contain_aturan_harga(): void
    {
        $navigation = config('navigation');
        $pricingGroup = collect($navigation)->firstWhere('label', 'Harga & Margin');

        $this->assertNotNull($pricingGroup);
        $childrenLabels = collect($pricingGroup['children'])->pluck('label')->all();

        $this->assertNotContains('Aturan Harga', $childrenLabels);
        $this->assertContains('Harga Produk', $childrenLabels);
        $this->assertContains('Histori Harga', $childrenLabels);
    }

    public function test_save_item_with_single_minimum_price_syncs_all_channels_and_records_history(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create([
            'name' => 'Kopi Arabika 250g',
            'sku' => 'KOP-ARA-250',
            'cost_price' => '25000.00',
            'base_unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->superAdmin);

        // 1. Initial save of single minimum price
        $response = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'price' => '35000',
            'unlimited' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'product_id' => $product->id,
        ]);

        // Verify all 3 channels have active price of 35000
        $retail = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring Retail')->first();
        $b2b = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring B2B')->first();
        $pos = ProductPrice::where('product_id', $product->id)->where('price_ring', 'Ring POS')->first();

        $this->assertNotNull($retail);
        $this->assertSame('35000.00', $retail->recommended_price);
        $this->assertSame('35000.00', $retail->min_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $retail->status);

        $this->assertNotNull($b2b);
        $this->assertSame('35000.00', $b2b->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $b2b->status);

        $this->assertNotNull($pos);
        $this->assertSame('35000.00', $pos->recommended_price);
        $this->assertSame(ProductPriceStatus::ACTIVE, $pos->status);

        // Verify initial PriceHistory recorded
        $history = PriceHistory::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame('35000.00', $history->new_price);
        $this->assertSame('initial', $history->trend());
        $this->assertSame('Harga Baru', $history->trendLabel());

        // 2. Update price from 35000 to 40000 (Naik)
        $updateResponse = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'price' => '40000',
            'unlimited' => true,
        ]);

        $updateResponse->assertOk();

        $latestHistory = PriceHistory::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($latestHistory);
        $this->assertSame('35000.00', $latestHistory->old_price);
        $this->assertSame('40000.00', $latestHistory->new_price);
        $this->assertSame('up', $latestHistory->trend());
        $this->assertSame('Naik', $latestHistory->trendLabel());
        $this->assertSame('5000.00', $latestHistory->priceDifference());

        // 3. Update price from 40000 to 38000 (Turun)
        $downResponse = $this->postJson(route('pricing.product-prices.save-item'), [
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'price' => '38000',
            'unlimited' => true,
        ]);

        $downResponse->assertOk();

        $downHistory = PriceHistory::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($downHistory);
        $this->assertSame('40000.00', $downHistory->old_price);
        $this->assertSame('38000.00', $downHistory->new_price);
        $this->assertSame('down', $downHistory->trend());
        $this->assertSame('Turun', $downHistory->trendLabel());
        $this->assertSame('-2000.00', $downHistory->priceDifference());
    }

    public function test_batch_save_updates_multiple_products_and_records_history(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);

        $prod1 = Product::factory()->create([
            'name' => 'Produk Batch 1',
            'sku' => 'BAT-001',
            'cost_price' => '10000.00',
            'base_unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $prod2 = Product::factory()->create([
            'name' => 'Produk Batch 2',
            'sku' => 'BAT-002',
            'cost_price' => '20000.00',
            'base_unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->postJson(route('pricing.product-prices.batch-save'), [
            'work_location_id' => $this->location->id,
            'items' => [
                ['product_id' => $prod1->id, 'price' => 15000],
                ['product_id' => $prod2->id, 'price' => 28000],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'updated_count' => 2,
        ]);

        $this->assertSame('15000.00', ProductPrice::where('product_id', $prod1->id)->where('price_ring', 'Ring Retail')->value('recommended_price'));
        $this->assertSame('28000.00', ProductPrice::where('product_id', $prod2->id)->where('price_ring', 'Ring Retail')->value('recommended_price'));

        $this->assertSame(1, PriceHistory::where('product_id', $prod1->id)->count());
        $this->assertSame(1, PriceHistory::where('product_id', $prod2->id)->count());
    }

    public function test_quick_setup_records_price_history_on_setup(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create([
            'name' => 'Produk Quick Setup History',
            'sku' => 'QSH-001',
            'cost_price' => '50000.00',
            'base_unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $service = app(ProductQuickSetupService::class);

        $result = $service->saveRow([
            'product_id' => $product->id,
            'hpp_unit_id' => $unit->id,
            'hpp_amount' => '50000.00',
            'stock_unit_id' => $unit->id,
            'stock_qty' => '10.0000',
            'retail_min_price' => '65000.00',
            'notes' => 'Test Quick Setup Price History',
        ], $this->superAdmin, $this->location);

        $this->assertSame($product->id, $result['product_id']);
        $this->assertSame('65000.00', $result['retail_min_price']);

        $history = PriceHistory::where('product_id', $product->id)->where('source', 'quick_setup')->first();
        $this->assertNotNull($history);
        $this->assertSame('65000.00', $history->new_price);
        $this->assertSame('50000.00', $history->hpp_snapshot);
        $this->assertSame($this->superAdmin->id, $history->changed_by);
    }

    public function test_price_history_page_filters_by_trend(): void
    {
        $unit = Unit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);
        $product = Product::factory()->create([
            'name' => 'Produk Filter Trend',
            'sku' => 'PFT-001',
            'base_unit_id' => $unit->id,
            'status' => 'active',
        ]);

        $price = ProductPrice::create([
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'channel' => 'retail',
            'price_ring' => 'Ring Retail',
            'recommended_price' => '10000.00',
            'status' => ProductPriceStatus::ACTIVE->value,
        ]);

        // Create 1 initial, 1 up, 1 down
        PriceHistory::create([
            'priceable_type' => ProductPrice::class,
            'priceable_id' => $price->id,
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'channel' => 'retail',
            'price_ring' => 'Ring Retail',
            'old_price' => null,
            'new_price' => '10000.00',
            'changed_by' => $this->superAdmin->id,
            'source' => 'product_prices',
            'reason' => 'Harga awal',
        ]);

        PriceHistory::create([
            'priceable_type' => ProductPrice::class,
            'priceable_id' => $price->id,
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'channel' => 'retail',
            'price_ring' => 'Ring Retail',
            'old_price' => '10000.00',
            'new_price' => '12000.00',
            'changed_by' => $this->superAdmin->id,
            'source' => 'product_prices',
            'reason' => 'Kenaikan',
        ]);

        PriceHistory::create([
            'priceable_type' => ProductPrice::class,
            'priceable_id' => $price->id,
            'product_id' => $product->id,
            'work_location_id' => $this->location->id,
            'channel' => 'retail',
            'price_ring' => 'Ring Retail',
            'old_price' => '12000.00',
            'new_price' => '11000.00',
            'changed_by' => $this->superAdmin->id,
            'source' => 'product_prices',
            'reason' => 'Penurunan',
        ]);

        $this->actingAs($this->superAdmin);

        // Filter: trend=up
        $resUp = $this->get(route('pricing.history.index', ['trend' => 'up']));
        $resUp->assertOk();
        $resUp->assertSee('Naik');
        $this->assertSame(1, $resUp->viewData('histories')->total());

        // Filter: trend=down
        $resDown = $this->get(route('pricing.history.index', ['trend' => 'down']));
        $resDown->assertOk();
        $resDown->assertSee('Turun');
        $this->assertSame(1, $resDown->viewData('histories')->total());

        // Filter: trend=initial
        $resInit = $this->get(route('pricing.history.index', ['trend' => 'initial']));
        $resInit->assertOk();
        $resInit->assertSee('Harga Baru');
        $this->assertSame(1, $resInit->viewData('histories')->total());
    }
}
