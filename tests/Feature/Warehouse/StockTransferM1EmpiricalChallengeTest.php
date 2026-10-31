<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Warehouse\StockTransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferM1EmpiricalChallengeTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $storeHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->transfers = app(StockTransferService::class);

        $this->warehouseHead = User::factory()->create(['is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));
        $this->storeHead = User::factory()->create(['is_active' => true]);
        $this->storeHead->assignRole(Role::findOrCreate('kepala_toko'));
    }

    public function test_location_options_without_bin_returns_aggregated_stock_and_preview(): void
    {
        [$warehouse, $branch, $product, $bin1] = $this->fixture();
        $bin2 = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'BIN-2']);
        $this->assignScope($warehouse, $branch);

        // Receive 10 in bin1, 15 in bin2 -> total 25
        $this->inventory->receive($product, $warehouse->workLocation, $bin1, '10', $this->warehouseHead);
        $this->inventory->receive($product, $warehouse->workLocation, $bin2, '15', $this->warehouseHead);

        $this->actingAs($this->warehouseStaff);

        // Test without warehouse_location_id
        $response = $this->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'context' => 'product',
        ]))->assertOk();

        $data = $response->json();
        $this->assertNotEmpty($data['results']);
        $item = collect($data['results'])->firstWhere('id', $product->id);
        $this->assertNotNull($item, 'Product must be found in options');
        $this->assertEquals(25.0, $item['available_quantity']);
        $this->assertSame('Stok: 25 Pcs', $item['stock_preview']);
        $this->assertStringContainsString('Stok: 25 Pcs', $item['text']);
        $this->assertNotEmpty($item['units']);
        $this->assertSame($product->baseUnit->name, $item['units'][0]['text']);
    }

    public function test_location_options_with_bin_returns_only_stock_in_specified_bin(): void
    {
        [$warehouse, $branch, $product, $bin1] = $this->fixture();
        $bin2 = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'BIN-2']);
        $this->assignScope($warehouse, $branch);

        $this->inventory->receive($product, $warehouse->workLocation, $bin1, '10', $this->warehouseHead);
        $this->inventory->receive($product, $warehouse->workLocation, $bin2, '15', $this->warehouseHead);

        $this->actingAs($this->warehouseStaff);

        // Query bin 1 only -> should return 10
        $responseBin1 = $this->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'warehouse_location_id' => $bin1->id,
            'context' => 'product',
        ]))->assertOk();

        $item1 = collect($responseBin1->json('results'))->firstWhere('id', $product->id);
        $this->assertEquals(10.0, $item1['available_quantity']);
        $this->assertSame('Stok: 10 Pcs', $item1['stock_preview']);

        // Query bin 2 only -> should return 15
        $responseBin2 = $this->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'warehouse_location_id' => $bin2->id,
            'context' => 'product',
        ]))->assertOk();

        $item2 = collect($responseBin2->json('results'))->firstWhere('id', $product->id);
        $this->assertEquals(15.0, $item2['available_quantity']);
        $this->assertSame('Stok: 15 Pcs', $item2['stock_preview']);
    }

    public function test_location_options_product_without_stock_is_excluded(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // Product has 0 stock received
        $this->actingAs($this->warehouseStaff);

        $response = $this->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'context' => 'product',
        ]))->assertOk();

        $item = collect($response->json('results'))->firstWhere('id', $product->id);
        $this->assertNull($item, 'Product without positive available stock should not be listed');
    }

    public function test_simplified_four_column_transfer_creation_with_header_source_bin(): void
    {
        [$warehouse, $branch, $product, $sourceBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $this->actingAs($this->warehouseStaff);

        // 4-column item: only product_id and quantity (no unit, no bin, no batch, no destination price/bin)
        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'submit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '4',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($response->isRedirect());
        $transfer = StockTransfer::query()->latest('id')->firstOrFail();

        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);
        $this->assertCount(1, $transfer->items);
        $item = $transfer->items->first();
        $this->assertSame('4.0000', (string) $item->quantity_requested);
        $this->assertSame('4.0000', (string) $item->quantity_approved);
        $this->assertNull($item->destination_warehouse_location_id);
        $this->assertNull($item->destination_sale_price);

        // Approve and verify layer allocation
        $approvedTransfer = $this->transfers->approve($transfer, $this->warehouseHead);
        $this->assertSame(StockTransferStatus::APPROVED, $approvedTransfer->status);

        $stock = Stock::query()->where('work_location_id', $warehouse->work_location_id)->where('warehouse_location_id', $sourceBin->id)->firstOrFail();
        $this->assertSame('4.0000', (string) $stock->quantity_reserved);
    }

    public function test_simplified_four_column_transfer_creation_unbinned_location(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        // Unbinned stock (warehouse_location = null)
        $this->inventory->receive($product, $warehouse->workLocation, null, '10', $this->warehouseHead);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'submit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '3',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($response->isRedirect());
        $transfer = StockTransfer::query()->latest('id')->firstOrFail();

        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);
        $this->assertCount(1, $transfer->items);
        $item = $transfer->items->first();
        $this->assertSame('3.0000', (string) $item->quantity_requested);
        $this->assertSame('3.0000', (string) $item->quantity_approved);

        $approvedTransfer = $this->transfers->approve($transfer, $this->warehouseHead);
        $this->assertSame(StockTransferStatus::APPROVED, $approvedTransfer->status);

        $stock = Stock::query()->where('work_location_id', $warehouse->work_location_id)->whereNull('warehouse_location_id')->firstOrFail();
        $this->assertSame('3.0000', (string) $stock->quantity_reserved);
    }

    public function test_edge_case_negative_quantity_rejected(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '-5',
                ],
            ],
            'action' => 'submit',
        ]);

        $response->assertSessionHasErrors('items.0.quantity_requested');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_edge_case_zero_quantity_rejected(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '0',
                ],
            ],
            'action' => 'submit',
        ]);

        $response->assertSessionHasErrors('items.0.quantity_requested');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_edge_case_same_source_and_destination_location_rejected(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $warehouse->work_location_id, // Same!
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '2',
                ],
            ],
            'action' => 'submit',
        ]);

        $response->assertSessionHasErrors([
            'destination_work_location_id' => 'Lokasi kerja tujuan tidak boleh sama dengan lokasi kerja sumber.',
        ]);
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_edge_case_approved_quantity_exceeding_requested_rejected(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity_requested' => '2',
                    'quantity_approved' => '5', // Greater!
                ],
            ],
            'action' => 'submit',
        ]);

        $response->assertSessionHasErrors('items.0.quantity_approved');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_edge_case_empty_items_rejected(): void
    {
        [$warehouse, $branch] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $this->actingAs($this->warehouseStaff);

        $response = $this->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [],
            'action' => 'submit',
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation} */
    private function fixture(): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-CHALLENGE']);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-CHALLENGE']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id, 'code' => 'GDG-CHALLENGE']);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id, 'code' => 'TKO-CHALLENGE']);

        $unit = Unit::factory()->create(['code' => 'PCS', 'name' => 'Pcs']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'PCS',
            'conversion_factor' => '1',
            'is_base' => true,
            'is_sellable' => true,
            'is_active' => true,
        ]);
        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'SRC-BIN']);

        return [$warehouse, $branch, $product, $sourceBin];
    }

    private function assignScope(Warehouse $warehouse, Branch $branch): void
    {
        foreach ([$this->warehouseHead, $this->warehouseStaff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        }

        $this->warehouseHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => false, 'is_active' => true]]);
        $this->storeHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => true, 'is_active' => true]]);
    }
}
