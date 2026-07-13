<?php

namespace Tests\Feature\Warehouse;

use App\Enums\RestockRequestStatus;
use App\Enums\StockTransferStatus;
use App\Exceptions\ServiceException;
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
use App\Services\Warehouse\RestockRequestService;
use App\Services\Warehouse\StockTransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private RestockRequestService $restocks;

    private StockTransferService $transfers;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $storeHead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->restocks = app(RestockRequestService::class);
        $this->transfers = app(StockTransferService::class);

        $this->warehouseHead = User::factory()->create(['is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));
        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));
        $this->storeHead = User::factory()->create(['is_active' => true]);
        $this->storeHead->assignRole(Role::findOrCreate('kepala_toko'));
    }

    public function test_p12_pages_can_be_opened(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '10', $this->warehouseHead);
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '3');

        $this->actingAs($this->warehouseHead);
        $this->get(route('retail.restock-requests.index'))->assertOk()->assertSee('Permintaan Restock Cabang');
        $this->get(route('warehouse.stock-transfers.index'))->assertOk()->assertSee('Daftar Transfer Stok')->assertSee($transfer->number);
        $this->get(route('warehouse.stock-transfers.create'))->assertOk()->assertSee('Form dan Approval Transfer');
        $this->get(route('warehouse.stock-transfers.show', $transfer))->assertOk()->assertSee('Detail Transfer dan Timeline');
        $this->get(route('warehouse.stock-transfers.packing', $transfer))->assertOk()->assertSee('Picking dan Packing');
    }

    public function test_restock_to_transfer_end_to_end_partial_and_full_receive(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, null, '10', $this->warehouseHead);

        $restock = $this->restocks->create($this->restockPayload($branch, $warehouse, $product, '6'), $this->storeHead);
        $this->assertSame(RestockRequestStatus::PENDING_APPROVAL, $restock->status);
        $approvedRequest = $this->restocks->approve($restock, $this->warehouseHead);
        $transfer = $this->transfers->createFromRestockRequest($approvedRequest, $this->warehouseHead);
        $this->transfers->approve($transfer, $this->warehouseHead);

        $sourceStock = Stock::query()->where('work_location_id', $warehouse->work_location_id)->firstOrFail();
        $this->assertSame('10.0000', $sourceStock->quantity_on_hand);
        $this->assertSame('6.0000', $sourceStock->quantity_reserved);

        $this->transfers->pack($transfer, ['package_no' => 'PKG-1', 'items' => [$transfer->items->first()->id => ['quantity_picked' => '6']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, ['carrier' => 'Internal', 'vehicle_number' => 'B 1234 CD'], $this->warehouseStaff);
        $sourceStock = $sourceStock->fresh();
        $this->assertSame('4.0000', $sourceStock->quantity_on_hand);
        $this->assertSame('0.0000', $sourceStock->quantity_reserved);

        $item = $transfer->fresh('items')->items->firstOrFail();
        $partial = $this->transfers->receive($transfer, ['idempotency_key' => 'receive-partial', 'items' => [$item->id => ['quantity_received' => '2', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']]], $this->storeHead);
        $this->assertSame(StockTransferStatus::PARTIALLY_RECEIVED, $partial->status);
        $this->assertSame('2.0000', Stock::query()->where('work_location_id', $branch->work_location_id)->firstOrFail()->quantity_on_hand);

        $item = $partial->items->firstOrFail();
        $full = $this->transfers->receive($partial, ['idempotency_key' => 'receive-final', 'items' => [$item->id => ['quantity_received' => '4', 'quantity_damaged' => '0', 'quantity_discrepancy' => '0']]], $this->storeHead);
        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $full->status);
        $completed = $this->transfers->complete($full, $this->storeHead);
        $this->assertSame(StockTransferStatus::COMPLETED, $completed->status);
        $this->assertSame('6.0000', Stock::query()->where('work_location_id', $branch->work_location_id)->firstOrFail()->quantity_on_hand);
    }

    public function test_cancel_approved_transfer_releases_reservation(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '5', $this->warehouseHead);
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '3');

        $this->transfers->cancel($transfer, $this->warehouseHead, 'Batal test');

        $stock = Stock::query()->where('work_location_id', $warehouse->work_location_id)->firstOrFail();
        $this->assertSame('5.0000', $stock->quantity_on_hand);
        $this->assertSame('0.0000', $stock->quantity_reserved);
        $this->assertSame(StockTransferStatus::CANCELLED, $transfer->fresh()->status);
    }

    public function test_over_pick_and_over_receive_are_rejected(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '5', $this->warehouseHead);
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '3');

        try {
            $this->transfers->pack($transfer, ['items' => [$transfer->items->first()->id => ['quantity_picked' => '4']]], $this->warehouseStaff);
            $this->fail('Over-pick seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('tidak boleh melebihi approved', $exception->getMessage());
        }

        $this->transfers->pack($transfer, ['items' => [$transfer->items->first()->id => ['quantity_picked' => '3']]], $this->warehouseStaff);
        $shipped = $this->transfers->ship($transfer, [], $this->warehouseStaff);
        $item = $shipped->items->firstOrFail();

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('melebihi qty dikirim');
        $this->transfers->receive($shipped, ['idempotency_key' => 'over-receive', 'items' => [$item->id => ['quantity_received' => '4']]], $this->storeHead);
    }

    public function test_discrepancy_is_accounted_without_adding_destination_stock(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '5', $this->warehouseHead);
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '3');
        $this->transfers->pack($transfer, ['items' => [$transfer->items->first()->id => ['quantity_picked' => '3']]], $this->warehouseStaff);
        $shipped = $this->transfers->ship($transfer, [], $this->warehouseStaff);
        $item = $shipped->items->firstOrFail();

        $received = $this->transfers->receive($shipped, ['idempotency_key' => 'discrepancy', 'items' => [$item->id => ['quantity_received' => '2', 'quantity_damaged' => '0', 'quantity_discrepancy' => '1']]], $this->storeHead);

        $this->assertSame(StockTransferStatus::FULLY_RECEIVED, $received->status);
        $this->assertSame('2.0000', Stock::query()->where('work_location_id', $branch->work_location_id)->firstOrFail()->quantity_on_hand);
        $this->assertSame('1.0000', $received->items->firstOrFail()->quantity_discrepancy);
    }

    public function test_branch_scope_blocks_other_branch_receive_page(): void
    {
        [$warehouse, $branch, $product, $sourceBin, $destinationBin] = $this->fixture();
        [$otherWarehouse, $otherBranch] = $this->warehouseAndBranch('B');
        $this->assignScope($warehouse, $branch);
        $this->inventory->receive($product, $warehouse->workLocation, $sourceBin, '5', $this->warehouseHead);
        $transfer = $this->makeApprovedTransfer($warehouse, $branch, $product, $sourceBin, $destinationBin, '1');
        $this->transfers->pack($transfer, ['items' => [$transfer->items->first()->id => ['quantity_picked' => '1']]], $this->warehouseStaff);
        $this->transfers->ship($transfer, [], $this->warehouseStaff);

        $otherStoreHead = User::factory()->create(['is_active' => true]);
        $otherStoreHead->assignRole(Role::findOrCreate('kepala_toko'));
        $otherStoreHead->workLocations()->syncWithoutDetaching([$otherBranch->work_location_id => ['is_default' => true, 'is_active' => true]]);

        $this->actingAs($otherStoreHead)
            ->get(route('retail.stock-transfers.receive-form', $transfer))
            ->assertForbidden();
    }

    /** @return array{Warehouse, Branch, Product, WarehouseLocation, WarehouseLocation} */
    private function fixture(): array
    {
        [$warehouse, $branch] = $this->warehouseAndBranch('A');
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id, 'status' => 'active']);
        ProductUnit::query()->create(['product_id' => $product->id, 'unit_id' => $unit->id, 'name' => 'PCS', 'conversion_factor' => '1', 'is_base' => true, 'is_sellable' => true, 'is_active' => true]);
        $sourceBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'SRC-BIN']);
        $destinationBin = WarehouseLocation::factory()->create(['warehouse_id' => $warehouse->id, 'full_code' => 'DST-BIN']);

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

    private function makeApprovedTransfer(Warehouse $warehouse, Branch $branch, Product $product, WarehouseLocation $sourceBin, WarehouseLocation $destinationBin, string $qty): StockTransfer
    {
        $transfer = $this->transfers->create([
            'source_work_location_id' => $warehouse->work_location_id,
            'source_warehouse_location_id' => $sourceBin->id,
            'destination_work_location_id' => $branch->work_location_id,
            'destination_warehouse_location_id' => null,
            'transfer_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'quantity_approved' => $qty,
                'source_warehouse_location_id' => $sourceBin->id,
                'destination_warehouse_location_id' => null,
            ]],
            'action' => 'submit',
        ], $this->warehouseStaff);

        return $this->transfers->approve($transfer, $this->warehouseHead);
    }

    /** @return array<string, mixed> */
    private function restockPayload(Branch $branch, Warehouse $warehouse, Product $product, string $qty): array
    {
        return [
            'branch_id' => $branch->id,
            'source_warehouse_id' => $warehouse->id,
            'priority' => 'normal',
            'needed_at' => now()->addDay()->toDateString(),
            'action' => 'submit',
            'items' => [[
                'product_id' => $product->id,
                'quantity_requested' => $qty,
                'priority' => 'normal',
            ]],
        ];
    }
}
