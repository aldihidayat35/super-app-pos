<?php

namespace Tests\Feature\Retail;

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

class StockTransferReceiveM2EmpiricalTest extends TestCase
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

    public function test_receive_blade_view_renders_successfully_without_blank_page_or_syntax_leaks(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeShippedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();

        $response = $this->actingAs($this->storeHead)
            ->get(route('retail.stock-transfers.receive-form', $transfer));

        $response->assertOk();
        $html = $response->getContent();

        // 1. Verify that the page is not blank and rendered full Metronic layout
        $this->assertNotEmpty($html);
        $this->assertGreaterThan(5000, strlen($html), 'View rendering must produce full layout (>5KB), not a blank page.');
        $this->assertStringContainsString('kt_app_content', $html);
        $this->assertStringContainsString('Penerimaan Transfer', $html);
        $this->assertStringContainsString('Item yang Diterima', $html);

        // 2. Verify no raw Blade directives or component tags leak into final HTML
        $this->assertStringNotContainsString('@extends', $html);
        $this->assertStringNotContainsString('@section', $html);
        $this->assertStringNotContainsString('<x-metronic', $html);
        $this->assertStringNotContainsString('<x-slot', $html);

        // 3. Verify all essential interactive elements and selectors are present in HTML
        $this->assertStringContainsString('id="btn-receive-all"', $html);
        $this->assertStringContainsString('id="btn-receive-all-inline"', $html);
        $this->assertStringContainsString('1-Klik: Terima Semua Sesuai Pengiriman', $html);
        $this->assertStringContainsString('id="summary-total-in-transit"', $html);
        $this->assertStringContainsString('id="summary-total-received"', $html);
        $this->assertStringContainsString('id="summary-total-damaged"', $html);
        $this->assertStringContainsString('id="summary-total-discrepancy"', $html);
        $this->assertStringContainsString('id="summary-status-alert"', $html);
        $this->assertStringContainsString('id="alert-state-green"', $html);
        $this->assertStringContainsString('id="btn-submit-receive"', $html);

        // 4. Verify table and item row attributes
        $this->assertStringContainsString('class="stock-transfer-item-row"', $html);
        $this->assertStringContainsString('data-item-id="'.$item->id.'"', $html);
        $this->assertStringContainsString('data-in-transit="5.0000"', $html);
        $this->assertStringContainsString('class="form-control form-control-sm receive-qty-input quantity-received"', $html);
        $this->assertStringContainsString('class="form-control form-control-sm receive-qty-input quantity-damaged"', $html);
        $this->assertStringContainsString('class="form-control form-control-sm receive-qty-input quantity-discrepancy"', $html);
        $this->assertStringContainsString('class="text-danger fs-8 fw-bold mt-1 d-none row-exceeded-msg"', $html);
        $this->assertStringContainsString('Melebihi sisa kirim (5)', $html);

        // 5. Verify embedded JS script contains all required functions
        $this->assertStringContainsString('function parseNum(val)', $html);
        $this->assertStringContainsString('function formatNumber(val)', $html);
        $this->assertStringContainsString('function updateRowValidation(row)', $html);
        $this->assertStringContainsString('function updateSummary()', $html);
        $this->assertStringContainsString('.btn-receive-all-action', $html);
        $this->assertStringContainsString('.quantity-damaged', $html);
        $this->assertStringContainsString('.quantity-discrepancy', $html);
        $this->assertStringContainsString('.quantity-received', $html);
    }

    public function test_receive_form_with_multiple_items_renders_all_rows_and_in_transit_data(): void
    {
        [$warehouse, $branch, $product1, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        $unit = Unit::factory()->create(['code' => 'BOX']);
        $product2 = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create(['product_id' => $product2->id, 'unit_id' => $unit->id, 'name' => 'BOX', 'conversion_factor' => '1', 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);

        $this->inventory->receive($product1, $warehouse->workLocation, $sourceBin, '20', $this->warehouseHead);
        $this->inventory->receive($product2, $warehouse->workLocation, $sourceBin, '15', $this->warehouseHead);

        // Create transfer with 2 items: 8 pcs of product1, 4 boxes of product2
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity_requested' => '8',
                    'quantity_approved' => '8',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity_requested' => '4',
                    'quantity_approved' => '4',
                    'source_warehouse_location_id' => $sourceBin->id,
                    'destination_warehouse_location_id' => $destinationBin->id,
                ],
            ],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $transfer = $this->transfers->approve($transfer, $this->warehouseHead);
        $items = $transfer->fresh('items')->items;
        $this->transfers->pack($transfer, [
            'items' => [
                $items[0]->id => ['quantity_picked' => '8'],
                $items[1]->id => ['quantity_picked' => '4'],
            ],
        ], $this->warehouseStaff);
        $transfer = $this->transfers->ship($transfer, ['carrier' => 'Kurir Express'], $this->warehouseStaff);

        $response = $this->actingAs($this->storeHead)
            ->get(route('retail.stock-transfers.receive-form', $transfer));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('data-in-transit="8.0000"', $html);
        $this->assertStringContainsString('data-in-transit="4.0000"', $html);
        $this->assertStringContainsString($product1->sku, $html);
        $this->assertStringContainsString($product2->sku, $html);
    }

    public function test_receive_form_submission_with_discrepancy_and_damaged_updates_inventory_and_status(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);

        $transfer = $this->makeShippedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '5');
        $item = $transfer->items->firstOrFail();

        // Simulate receiving with 3 good, 1 damaged, 1 discrepancy (total = 5)
        $payload = [
            'idempotency_key' => (string) str()->uuid(),
            'items' => [
                $item->id => [
                    'quantity_received' => '3',
                    'quantity_damaged' => '1',
                    'quantity_discrepancy' => '1',
                    'notes' => '1 rusak terbentur, 1 hilang selisih',
                ],
            ],
        ];

        $response = $this->actingAs($this->storeHead)
            ->post(route('retail.stock-transfers.receive', $transfer), $payload);

        $response->assertRedirect(route('retail.stock-transfers.receiving'))
            ->assertSessionHas('notification.type', 'success');

        // Destination stock on hand: 3 (received) + 1 (damaged physically stored) = 4.0000
        $stock = Stock::query()
            ->where('product_id', $product->id)
            ->where('work_location_id', $branch->work_location_id)
            ->firstOrFail();

        $this->assertSame('4.0000', $stock->quantity_on_hand);
        $this->assertSame('1.0000', $stock->quantity_damaged);
        $this->assertSame('3.0000', $stock->available_quantity);

        // Item tracked quantities
        $freshItem = $item->fresh();
        $this->assertSame('3.0000', (string) $freshItem->quantity_received);
        $this->assertSame('1.0000', (string) $freshItem->quantity_damaged);
        $this->assertSame('1.0000', (string) $freshItem->quantity_discrepancy);
        $this->assertSame('0.0000', $freshItem->inTransitQuantity());

        // Transfer with discrepancy requires resolution before fully received -> status is PARTIALLY_RECEIVED
        $this->assertSame(StockTransferStatus::PARTIALLY_RECEIVED, $transfer->fresh()->status);
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation, WarehouseLocation} */
    private function fixture(): array
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('EMPIRICAL');
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $unit->id, 'name' => 'PCS', 'conversion_factor' => '1', 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);
        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'SRC-EMP']);
        $destinationBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'DST-EMP']);

        return [$warehouse, $branch, $product, $sourceBin, $destinationBin];
    }

    /** @return array{Warehouse, Branch} */
    private function warehouseAndBranch(string $suffix): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => "GDG-$suffix"]);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => "TKO-$suffix"]);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id, 'code' => "GDG-$suffix"]);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id, 'code' => "TKO-$suffix"]);

        return [$warehouse, $branch];
    }

    private function assignScope(Warehouse $warehouse, Branch $branch): void
    {
        foreach ([$this->warehouseHead, $this->warehouseStaff] as $user) {
            $user->workLocations()->syncWithoutDetaching([$warehouse->work_location_id => ['is_default' => true, 'is_active' => true]]);
        }

        $this->warehouseHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => false, 'is_active' => true]]);
        $this->storeHead->workLocations()->syncWithoutDetaching([$branch->work_location_id => ['is_default' => true, 'is_active' => true]]);
    }

    private function makeShippedTransfer(Warehouse $warehouse, Branch $branch, Product $product, WarehouseLocation $sourceBin, WarehouseLocation $destinationBin, string $qty): StockTransfer
    {
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => $destinationBin->id,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'quantity_approved' => $qty,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => $destinationBin->id,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        $transfer = $this->transfers->approve($transfer, $this->warehouseHead);
        $this->transfers->pack($transfer, ['items' => [$transfer->items->first()->id => ['quantity_picked' => $qty]]], $this->warehouseStaff);

        return $this->transfers->ship($transfer, ['carrier' => 'Ekspedisi Logistik'], $this->warehouseStaff);
    }
}
