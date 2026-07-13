<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ServiceException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Services\Warehouse\StockOpnameService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockOpnameWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private StockOpnameService $opnames;

    private User $warehouseHead;

    private User $warehouseStaff;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->inventory = app(InventoryService::class);
        $this->opnames = app(StockOpnameService::class);

        $this->warehouseHead = User::factory()->create(['is_active' => true]);
        $this->warehouseHead->assignRole(Role::findOrCreate('kepala_gudang'));

        $this->warehouseStaff = User::factory()->create(['is_active' => true]);
        $this->warehouseStaff->assignRole(Role::findOrCreate('staff_gudang'));

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->assignRole(Role::findOrCreate('owner_approver'));
    }

    public function test_p13_pages_can_be_opened(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-PAGE');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);

        $this->actingAs($this->warehouseHead);

        $this->get(route('warehouse.stock-opnames.index'))->assertOk()->assertSee('Stok Opname');
        $this->get(route('warehouse.stock-opnames.show', $opname))->assertOk()->assertSee($opname->number);
        $this->get(route('warehouse.stock-opnames.count', $opname))->assertOk()->assertSee('Counting '.$opname->number);
        $this->get(route('warehouse.stock-opnames.variance', $opname))->assertOk()->assertSee('Variance '.$opname->number);
        $this->get(route('warehouse.stock-opnames.approval', $opname))->assertOk()->assertSee('Approval '.$opname->number);
        $this->get(route('warehouse.stock-opnames.report', $opname))->assertOk()->assertSee('Berita Acara Stok Opname');
    }

    public function test_opname_completion_adjusts_stock_once_and_writes_append_only_mutation(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-ADJ');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);
        $item = $opname->items->firstOrFail();

        $this->opnames->countItem($item, [
            'counted_qty' => '8',
            'reason' => StockOpnameReason::LOST->value,
            'note' => 'Selisih fisik',
        ], $this->warehouseStaff);
        $submitted = $this->opnames->submit($opname, $this->warehouseStaff);
        $approved = $this->opnames->approve($submitted, $this->warehouseHead, 'Disetujui test');
        $completed = $this->opnames->complete($approved, $this->warehouseHead);
        $this->opnames->complete($completed, $this->warehouseHead);

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('8.0000', $stock->quantity_on_hand);
        $this->assertSame(StockOpnameStatus::COMPLETED, $completed->fresh()->status);
        $this->assertSame(1, StockMutation::query()->where('reference_type', 'stock_opname')->where('reference_id', $opname->id)->count());
        $this->assertDatabaseCount('stock_mutations', 2);
    }

    public function test_transaction_after_snapshot_is_flagged_before_submit(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-WARN');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);
        $item = $opname->items->firstOrFail();

        $this->travel(1)->minutes();
        $this->inventory->receive($product, $workLocation, $bin, '2', $this->warehouseHead, ['type' => 'manual_after_snapshot', 'no' => 'AFTER-1']);

        $this->opnames->countItem($item, ['counted_qty' => '12', 'reason' => StockOpnameReason::OTHER->value], $this->warehouseStaff);
        $this->opnames->submit($opname, $this->warehouseStaff);

        $this->assertTrue($item->fresh()->has_transaction_after_snapshot);
    }

    public function test_freeze_stock_blocks_non_opname_mutation_until_completed(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-FRZ');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->opnames->create([
            'work_location_id' => $workLocation->id,
            'warehouse_location_id' => $bin->id,
            'method' => 'manual',
            'freeze_stock' => true,
            'action' => 'start',
        ], $this->warehouseHead);

        try {
            $this->inventory->issue($product, $workLocation, $bin, '1', $this->warehouseHead, ['type' => 'manual', 'no' => 'ISS-FREEZE']);
            $this->fail('Mutasi non-opname saat freeze seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('dibekukan', $exception->getMessage());
        }

        $this->opnames->countItem($opname->items->firstOrFail(), ['counted_qty' => '9', 'reason' => StockOpnameReason::LOST->value], $this->warehouseStaff);
        $submitted = $this->opnames->submit($opname, $this->warehouseStaff);
        $approved = $this->opnames->approve($submitted, $this->warehouseHead, 'Disetujui');
        $this->opnames->complete($approved, $this->warehouseHead);

        $this->assertSame('9.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
    }

    public function test_threshold_variance_requires_owner_approval(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-OWNER', '250000.00');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '20', $this->warehouseHead);
        $opname = $this->opnames->create([
            'work_location_id' => $workLocation->id,
            'warehouse_location_id' => $bin->id,
            'method' => 'manual',
            'threshold_qty' => '1',
            'threshold_value' => '1000',
            'action' => 'start',
        ], $this->warehouseHead);
        $item = $opname->items->firstOrFail();

        $this->opnames->countItem($item, ['counted_qty' => '10', 'reason' => StockOpnameReason::LOST->value], $this->warehouseStaff);
        $submitted = $this->opnames->submit($opname, $this->warehouseStaff);

        $this->assertTrue($submitted->requires_owner_approval);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('membutuhkan approval owner');
        $this->opnames->approve($submitted, $this->warehouseHead, 'Harusnya ditolak');
    }

    public function test_owner_can_approve_high_variance_and_reject_does_not_mutate_stock(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-REJECT');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);
        $item = $opname->items->firstOrFail();

        $this->opnames->countItem($item, ['counted_qty' => '9', 'reason' => StockOpnameReason::LOST->value], $this->warehouseStaff);
        $submitted = $this->opnames->submit($opname, $this->warehouseStaff);
        $rejected = $this->opnames->reject($submitted, $this->warehouseHead, 'Hitung ulang');

        $this->assertSame(StockOpnameStatus::REJECTED, $rejected->status);
        $this->assertSame('10.0000', Stock::query()->where('product_id', $product->id)->firstOrFail()->quantity_on_hand);
        $this->assertSame(0, StockMutation::query()->where('reference_type', 'stock_opname')->where('reference_id', $opname->id)->count());

        [$product2, $workLocation2, $bin2] = $this->fixture('PRD-OPN-OWNEROK', '250000.00');
        $this->assignScope($workLocation2);
        $this->inventory->receive($product2, $workLocation2, $bin2, '10', $this->warehouseHead);
        $opname2 = $this->opnames->create([
            'work_location_id' => $workLocation2->id,
            'warehouse_location_id' => $bin2->id,
            'method' => 'manual',
            'threshold_qty' => '1',
            'threshold_value' => '1000',
            'action' => 'start',
        ], $this->warehouseHead);
        $this->opnames->countItem($opname2->items->firstOrFail(), ['counted_qty' => '8', 'reason' => StockOpnameReason::LOST->value], $this->warehouseStaff);
        $submitted2 = $this->opnames->submit($opname2, $this->warehouseStaff);
        $approved = $this->opnames->approve($submitted2, $this->owner, 'Owner approved');

        $this->assertSame(StockOpnameStatus::APPROVED, $approved->status);
    }

    public function test_invalid_import_and_multi_counter_lock_are_rejected(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-LOCK');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);
        $item = $opname->items->firstOrFail();

        try {
            $this->opnames->importCounts($opname, [['sku' => '', 'counted_qty' => 'x']], $this->warehouseStaff);
            $this->fail('Import invalid seharusnya ditolak.');
        } catch (ServiceException $exception) {
            $this->assertStringContainsString('Import invalid', $exception->getMessage());
        }

        $this->opnames->countItem($item, ['counted_qty' => '10'], $this->warehouseStaff);
        $otherCounter = User::factory()->create(['is_active' => true]);
        $otherCounter->assignRole(Role::findOrCreate('staff_gudang'));
        $otherCounter->workLocations()->syncWithoutDetaching([$workLocation->id => ['is_default' => true, 'is_active' => true]]);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('counter lain');
        $this->opnames->countItem($item, ['counted_qty' => '9'], $otherCounter);
    }

    public function test_location_scope_blocks_unassigned_user_from_opname_pages(): void
    {
        [$product, $workLocation, $bin] = $this->fixture('PRD-OPN-SCOPE');
        $this->assignScope($workLocation);
        $this->inventory->receive($product, $workLocation, $bin, '10', $this->warehouseHead);
        $opname = $this->createCountingOpname($workLocation, $bin);

        $outsider = User::factory()->create(['is_active' => true]);
        $outsider->assignRole(Role::findOrCreate('staff_gudang'));

        $this->actingAs($outsider)
            ->get(route('warehouse.stock-opnames.show', $opname))
            ->assertForbidden();
    }

    /** @return array{Product, WorkLocation, WarehouseLocation} */
    private function fixture(string $sku, string $costPrice = '10000.00'): array
    {
        $workLocation = WorkLocation::factory()->create(['type' => 'warehouse', 'code' => 'GDG-'.substr($sku, -6)]);
        $warehouse = Warehouse::factory()->create(['work_location_id' => $workLocation->id, 'code' => 'WH-'.substr($sku, -6)]);
        $bin = WarehouseLocation::factory()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'BIN-01',
            'full_code' => 'BIN-'.$sku,
        ]);
        $product = Product::factory()->create(['sku' => $sku, 'cost_price' => $costPrice]);

        return [$product, $workLocation, $bin];
    }

    private function createCountingOpname(WorkLocation $workLocation, WarehouseLocation $bin): StockOpname
    {
        return $this->opnames->create([
            'work_location_id' => $workLocation->id,
            'warehouse_location_id' => $bin->id,
            'method' => 'manual',
            'threshold_qty' => '10',
            'threshold_value' => '1000000',
            'action' => 'start',
        ], $this->warehouseHead);
    }

    private function assignScope(WorkLocation $workLocation): void
    {
        foreach ([$this->warehouseHead, $this->warehouseStaff, $this->owner] as $user) {
            $user->workLocations()->syncWithoutDetaching([$workLocation->id => ['is_default' => true, 'is_active' => true]]);
        }
    }
}
