<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\Inventory\StockLayerService;
use App\Services\Pricing\LocationProductPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockLayerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_hpp_layers_default_to_fifo_and_keep_their_cost(): void
    {
        $product = Product::factory()->create(['cost_price' => '9000.00']);
        $location = WorkLocation::factory()->create(['type' => 'warehouse']);
        $stock = Stock::query()->create([
            'product_id' => $product->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'location_scope_key' => "work:{$location->id}|bin:none",
            'quantity_on_hand' => '15.0000',
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'cost_value' => '170000.00',
        ]);
        $old = $this->layer($product, $stock, $location, 'BATCH-LAMA', '10000.00', '5.0000');
        $new = $this->layer($product, $stock, $location, 'BATCH-BARU', '12000.00', '10.0000');
        $service = app(StockLayerService::class);

        $allocations = $service->resolveLayers($product->id, $location->id, '7.0000');

        $this->assertSame($old->id, $allocations[0]['batch']->id);
        $this->assertSame($new->id, $allocations[1]['batch']->id);
        $this->assertSame('5.0000', $allocations[0]['quantity']);
        $this->assertSame('2.0000', $allocations[1]['quantity']);
        $this->assertSame('10571.43', $service->weightedAverageCost($allocations));
        $this->assertSame('170000.00', $service->inventoryValue($location->id, $product->id));
        $this->assertSame('9000.00', $product->fresh()->cost_price);
    }

    public function test_sale_price_is_isolated_per_work_location(): void
    {
        $product = Product::factory()->create(['cost_price' => '10000.00']);
        $warehouse = WorkLocation::factory()->create(['type' => 'warehouse']);
        $branch = WorkLocation::factory()->create(['type' => 'branch']);
        $actor = User::factory()->create();
        $prices = app(LocationProductPriceService::class);

        $prices->setActivePrice($product, $warehouse, '15000.00', $actor, 'test');
        $prices->setActivePrice($product, $branch, '16000.00', $actor, 'test');

        $this->assertSame('15000.00', $prices->activePrice($product, $warehouse));
        $this->assertSame('16000.00', $prices->activePrice($product, $branch));
        $this->assertDatabaseCount('price_histories', 2);
    }

    private function layer(
        Product $product,
        Stock $stock,
        WorkLocation $location,
        string $batchNo,
        string $cost,
        string $quantity,
    ): StockBatch {
        return StockBatch::query()->create([
            'product_id' => $product->id,
            'stock_id' => $stock->id,
            'work_location_id' => $location->id,
            'warehouse_location_id' => null,
            'source_type' => 'test_receipt',
            'unit_id' => $product->base_unit_id,
            'conversion_factor_snapshot' => '1.000000',
            'batch_no' => $batchNo,
            'received_at' => now()->toDateString(),
            'cost_price' => $cost,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.0000',
            'quantity_damaged' => '0.0000',
            'status' => 'active',
        ]);
    }
}
