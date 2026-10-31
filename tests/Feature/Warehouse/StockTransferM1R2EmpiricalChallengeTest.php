<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockTransferStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
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

class StockTransferM1R2EmpiricalChallengeTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

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
    }

    /**
     * Empirical Verification 1:
     * Endpoint locationOptions MUST return units[0].id as an integer (e.g. 1), not null.
     * Secondary units MUST also have valid integer IDs and decimal conversion factors.
     * Inactive units MUST be excluded from the returned options.
     */
    public function test_location_options_units_schema_and_integer_ids_and_factors(): void
    {
        [$warehouse, $branch, $product, $sourceBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '100', $this->warehouseHead);

        // Create 2 active secondary units and 1 inactive secondary unit
        $boxUnit = Unit::factory()->create(['code' => 'BOX', 'name' => 'Box', 'is_active' => true]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $boxUnit->id,
            'name' => 'Box',
            'conversion_factor' => '10.000000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $packUnit = Unit::factory()->create(['code' => 'PACK', 'name' => 'Pack', 'is_active' => true]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $packUnit->id,
            'name' => 'Pack',
            'conversion_factor' => '2.500000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $inactiveUnit = Unit::factory()->create(['code' => 'OLD', 'name' => 'Old Unit', 'is_active' => true]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $inactiveUnit->id,
            'name' => 'Old Unit',
            'conversion_factor' => '5.000000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => false, // Inactive!
        ]);

        $response = $this->actingAs($this->warehouseStaff)->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'warehouse_location_id' => $sourceBin->id,
            'context' => 'product',
            'q' => $product->sku,
        ]))->assertOk();

        $productData = $response->json('results.0');
        $this->assertNotNull($productData);
        $this->assertSame($product->id, $productData['id']);

        $units = $productData['units'];
        $this->assertIsArray($units);
        // Base unit + 2 active secondary units = 3 units total (inactive unit excluded)
        $this->assertCount(3, $units, 'Inactive secondary unit must not be included');

        // 1. Base unit checks
        $baseUnitOption = $units[0];
        $this->assertNotNull($baseUnitOption['id'], 'Base unit ID must not be null');
        $this->assertIsInt($baseUnitOption['id'], 'Base unit ID must be an integer');
        $this->assertSame($product->base_unit_id, $baseUnitOption['id']);
        $this->assertSame('1.000000', $baseUnitOption['factor']);
        $this->assertSame($product->baseUnit->name, $baseUnitOption['text']);

        // 2. Secondary unit 1 (Box)
        $boxOption = collect($units)->firstWhere('id', $boxUnit->id);
        $this->assertNotNull($boxOption);
        $this->assertIsInt($boxOption['id']);
        $this->assertSame('10.000000', $boxOption['factor']);
        $this->assertSame('Box', $boxOption['text']);

        // 3. Secondary unit 2 (Pack)
        $packOption = collect($units)->firstWhere('id', $packUnit->id);
        $this->assertNotNull($packOption);
        $this->assertIsInt($packOption['id']);
        $this->assertSame('2.500000', $packOption['factor']);
        $this->assertSame('Pack', $packOption['text']);

        // 4. Inactive unit check
        $inactiveOption = collect($units)->firstWhere('id', $inactiveUnit->id);
        $this->assertNull($inactiveOption, 'Inactive unit must not appear in units array');
    }

    /**
     * Empirical Verification 2:
     * StoreStockTransferRequest submission with base unit_id from locationOptions
     * MUST succeed without 422 validation errors.
     */
    public function test_store_transfer_request_with_base_unit_from_location_options(): void
    {
        [$warehouse, $branch, $product, $sourceBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '50', $this->warehouseHead);

        // Fetch options dynamically
        $optionsResponse = $this->actingAs($this->warehouseStaff)->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'context' => 'product',
            'q' => $product->sku,
        ]))->assertOk();

        $baseUnitId = $optionsResponse->json('results.0.units.0.id');
        $this->assertIsInt($baseUnitId);

        // Submit form
        $response = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'submit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $baseUnitId,
                    'quantity' => '10',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);
        $this->assertCount(1, $transfer->items);
        $item = $transfer->items->first();
        $this->assertSame($product->base_unit_id, $item->unit_id);
        $this->assertSame('10.0000', (string) $item->quantity_requested);
        $this->assertSame('10.0000', (string) $item->quantity_approved);
        $this->assertSame('1.000000', (string) $item->conversion_factor_snapshot);
    }

    /**
     * Empirical Verification 3:
     * StoreStockTransferRequest submission with secondary unit_id from locationOptions
     * MUST succeed without 422 validation errors and store correct factor snapshot.
     */
    public function test_store_transfer_request_with_secondary_unit_from_location_options(): void
    {
        [$warehouse, $branch, $product, $sourceBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '60', $this->warehouseHead);

        $dusUnit = Unit::factory()->create(['code' => 'DUS', 'name' => 'Dus', 'is_active' => true]);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $dusUnit->id,
            'name' => 'Dus',
            'conversion_factor' => '12.000000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        // Fetch options dynamically
        $optionsResponse = $this->actingAs($this->warehouseStaff)->getJson(route('warehouse.stock-transfers.location-options', [
            'work_location_id' => $warehouse->work_location_id,
            'context' => 'product',
            'q' => $product->sku,
        ]))->assertOk();

        $secondaryUnitId = $optionsResponse->json('results.0.units.1.id');
        $this->assertIsInt($secondaryUnitId);
        $this->assertSame($dusUnit->id, $secondaryUnitId);

        // Submit form: 3 Dus = 36 PCS in base units
        $response = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'submit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $secondaryUnitId,
                    'quantity' => '3',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $this->assertSame(StockTransferStatus::PENDING_APPROVAL, $transfer->status);
        $item = $transfer->items->first();
        $this->assertSame($dusUnit->id, $item->unit_id);
        $this->assertSame('3.0000', (string) $item->quantity_requested);
        $this->assertSame('12.000000', (string) $item->conversion_factor_snapshot);
        $this->assertSame('Dus', $item->unit_name_snapshot);
    }

    /**
     * Empirical Verification 4:
     * FormRequest prepareForValidation cleanses string "null", empty string, "0", or numeric strings
     * to prevent 422 validation errors.
     */
    public function test_store_transfer_request_sanitizes_unit_id_inputs(): void
    {
        [$warehouse, $branch, $product, $sourceBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '50', $this->warehouseHead);

        // Scenario 1: unit_id as string "null" (classic browser WebIDL option value issue)
        $response1 = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => 'null',
                    'quantity' => '1',
                ],
            ],
        ]);
        $response1->assertSessionHasNoErrors();

        // Scenario 2: unit_id as empty string ""
        $response2 = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => '',
                    'quantity' => '1',
                ],
            ],
        ]);
        $response2->assertSessionHasNoErrors();

        // Scenario 3: unit_id as numeric string "1" (standard POST from HTML select)
        $response3 = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => (string) $product->base_unit_id,
                    'quantity' => '1',
                ],
            ],
        ]);
        $response3->assertSessionHasNoErrors();
    }

    /**
     * Empirical Verification 5 (Adversarial stress test):
     * Non-existent unit_id or inactive unit_id MUST be rejected with 422 validation error.
     */
    public function test_store_transfer_request_rejects_invalid_and_inactive_unit_ids(): void
    {
        [$warehouse, $branch, $product] = $this->fixture();
        $this->assignScope($warehouse, $branch);

        // 1. Non-existent unit_id
        $responseInvalid = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => 9999999, // Does not exist
                    'quantity' => '1',
                ],
            ],
        ]);
        $responseInvalid->assertSessionHasErrors('items.0.unit_id');

        // 2. Inactive unit_id
        $inactiveUnit = Unit::factory()->create(['code' => 'INACT', 'is_active' => false]);
        $responseInactive = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $inactiveUnit->id, // Inactive!
                    'quantity' => '1',
                ],
            ],
        ]);
        $responseInactive->assertSessionHasErrors('items.0.unit_id');
    }

    /**
     * Empirical Verification 6:
     * Multi-row submission with mixed base unit and secondary units.
     */
    public function test_store_transfer_request_with_multi_row_mixed_units(): void
    {
        [$warehouse, $branch, $product1, $sourceBin] = $this->fixture();
        $product2 = Product::factory()->create(['base_unit_id' => $product1->base_unit_id, 'status' => 'active']);
        $this->assignScope($warehouse, $branch);

        $this->inventory->receive($product1, $warehouse->workLocation, $sourceBin, '100', $this->warehouseHead);
        $this->inventory->receive($product2, $warehouse->workLocation, $sourceBin, '100', $this->warehouseHead);

        $dusUnit = Unit::factory()->create(['code' => 'DUS', 'name' => 'Dus', 'is_active' => true]);
        ProductUnit::query()->create([
            'product_id' => $product1->id,
            'unit_id' => $dusUnit->id,
            'name' => 'Dus',
            'conversion_factor' => '12.000000',
            'is_base' => false,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->warehouseStaff)->post(route('warehouse.stock-transfers.store'), [
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'transfer_date' => now()->toDateString(),
            'action' => 'submit',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'unit_id' => $product1->base_unit_id,
                    'quantity' => '5',
                ],
                [
                    'product_id' => $product1->id,
                    'unit_id' => $dusUnit->id,
                    'quantity' => '2',
                ],
                [
                    'product_id' => $product2->id,
                    'unit_id' => $product2->base_unit_id,
                    'quantity' => '15',
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $transfer = StockTransfer::query()->latest('id')->firstOrFail();
        $this->assertCount(3, $transfer->items);
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation} */
    private function fixture(): array
    {
        $warehouseLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-CHALLENGE-2']);
        $branchLocation = WorkLocation::factory()->create(['type' => 'branch', 'code' => 'TKO-CHALLENGE-2']);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $warehouseLocation->id, 'code' => 'GDG-CHALLENGE-2']);
        $branch = Branch::factory()->create(['work_location_id' => $branchLocation->id, 'primary_warehouse_id' => $warehouse->id, 'code' => 'TKO-CHALLENGE-2']);

        $unit = Unit::factory()->create(['code' => 'PCS', 'name' => 'Pcs', 'is_active' => true]);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'name' => 'Pcs',
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
    }
}
